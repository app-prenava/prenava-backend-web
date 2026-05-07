<?php

namespace App\Services;

use App\Models\Food;
use App\Models\MealPlan;
use App\Models\MealPlanItem;
use App\Models\StuntingPrediction;
use App\Models\UserFoodPreference;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class FoodRecommendationService
{
    private const MEAL_SLOTS = ['breakfast', 'lunch', 'dinner', 'snack'];

    /**
     * SHAP factor → nutrient priority mapping.
     * Each factor maps to an ordered list of nutrients to prioritize.
     */
    private const FACTOR_NUTRIENT_MAP = [
        'mother_height_cm'         => ['protein', 'calories'],
        'mother_education_level'   => ['protein', 'calories'],
        'mother_employment_status' => ['protein', 'calories'],
        'improved_water'           => ['protein', 'calories'],
        'improved_sanitation'      => ['protein', 'calories'],
        'home_ownership'           => ['protein', 'calories'],
        'has_electricity'          => ['protein', 'calories'],
        'has_refrigerator'         => ['protein', 'calories'],
        'has_tv'                   => ['protein', 'calories'],
        'mother_age_at_birth'      => ['protein', 'calories'],
        'is_teenage_mother'        => ['protein', 'calories', 'carbohydrates'],
        'is_high_risk_mother_age'  => ['protein', 'calories'],
        'has_delivery_insurance'   => ['protein', 'calories'],
        'child_gender'             => ['protein', 'calories'],
        'anc_clinic_midwife'       => ['protein', 'calories'],
        'anc_hospital'             => ['protein', 'calories'],
        'anc_traditional_other'    => ['protein', 'calories'],
        'anc_unknown'              => ['protein', 'calories'],
    ];

    /**
     * Human-readable factor names for mobile display.
     */
    private const FACTOR_LABELS = [
        'mother_height_cm'         => 'Tinggi badan ibu',
        'mother_education_level'   => 'Pendidikan ibu',
        'mother_employment_status' => 'Status pekerjaan ibu',
        'improved_water'           => 'Akses air bersih',
        'improved_sanitation'      => 'Akses sanitasi',
        'home_ownership'           => 'Kepemilikan rumah',
        'has_electricity'          => 'Listrik',
        'has_refrigerator'         => 'Kulkas',
        'has_tv'                   => 'TV',
        'mother_age_at_birth'      => 'Usia ibu saat melahirkan',
        'is_teenage_mother'        => 'Ibu usia remaja',
        'is_high_risk_mother_age'  => 'Usia risiko tinggi',
        'has_delivery_insurance'   => 'Asuransi persalinan',
        'child_gender'             => 'Jenis kelamin anak',
        'anc_clinic_midwife'       => 'Pemeriksaan di klinik/bidan',
        'anc_hospital'             => 'Pemeriksaan di RS',
        'anc_traditional_other'    => 'Pemeriksaan tradisional',
        'anc_unknown'              => 'Tempat ANC tidak diketahui',
    ];

    /**
     * Reason templates for different factors.
     */
    private const FACTOR_REASONS = [
        'mother_height_cm'         => 'Mendukung kebutuhan protein tinggi untuk pertumbuhan optimal janin',
        'mother_education_level'   => 'Sumber nutrisi terjangkau dan mudah diakses',
        'mother_employment_status' => 'Makanan bergizi yang praktis disiapkan',
        'improved_water'           => 'Makanan bersih dan mudah diolah dengan higienis',
        'improved_sanitation'      => 'Makanan aman dengan kandungan gizi tinggi',
        'home_ownership'           => 'Sumber protein terjangkau untuk keluarga',
        'has_electricity'          => 'Makanan tahan lama yang tidak perlu pendinginan',
        'has_refrigerator'         => 'Makanan dengan daya simpan baik',
        'has_tv'                   => 'Sumber nutrisi penting untuk ibu hamil',
        'mother_age_at_birth'      => 'Makanan padat gizi untuk mendukung kehamilan',
        'is_teenage_mother'        => 'Nutrisi tinggi untuk mendukung pertumbuhan ibu muda dan janin',
        'is_high_risk_mother_age'  => 'Makanan kaya nutrisi untuk kehamilan usia risiko tinggi',
        'has_delivery_insurance'   => 'Sumber protein terjangkau untuk keluarga',
        'child_gender'             => 'Makanan bergizi seimbang untuk perkembangan janin',
    ];

    /**
     * Generate food recommendations from a prediction's SHAP factors.
     *
     * @param StuntingPrediction $prediction
     * @param int $limit Max foods to return
     * @return array{foods: array, nutrient_priorities: array, factor_labels: array}
     */
    public function recommend(StuntingPrediction $prediction, int $limit = 5, ?UserFoodPreference $preference = null): array
    {
        $shapFactors = $this->extractShapFactors($prediction);
        $priorities  = $this->determineNutrientPriorities($shapFactors);
        $foods       = $this->pickDiverseFoods($priorities, $limit, $preference);

        $reason = $this->buildReason($shapFactors);

        $formattedFoods = $foods->map(function (Food $food) use ($reason) {
            $item = $this->foodToArray($food);
            $item['reason'] = $reason;
            return $item;
        })->toArray();

        return [
            'foods'              => $formattedFoods,
            'nutrient_priorities' => $priorities,
            'factor_labels'      => $this->getFactorLabels($shapFactors),
        ];
    }

    /**
     * Generate lightweight recommendations for low-risk predictions.
     */
    public function recommendLowRisk(int $limit = 3, ?UserFoodPreference $preference = null): array
    {
        $foods = $this->pickDiverseFoods(['protein', 'calories'], $limit, $preference);

        return $foods->map(function (Food $food) {
            $item = $this->foodToArray($food);
            $item['reason'] = 'Makanan bergizi untuk mendukung kehamilan sehat';
            return $item;
        })->toArray();
    }

    /**
     * Generate a one-day menu schedule using weighted nutrient priorities.
     *
     * @return array{targets: array, meals: array, notes: array}
     */
    public function generateDailyMealPlan(StuntingPrediction $prediction): array
    {
        $shapFactors = $this->extractShapFactors($prediction);
        $priorities  = $this->determineNutrientPriorities($shapFactors);
        $riskLabel   = $prediction->risk_label ?? 'low_risk';

        $targets = $riskLabel === 'high_risk'
            ? ['calories' => 2200, 'protein' => 75, 'iron' => 27, 'calcium' => 1000]
            : ['calories' => 2000, 'protein' => 65, 'iron' => 24, 'calcium' => 900];

        $usedIds = [];
        $meals = [];

        foreach (self::MEAL_SLOTS as $slot) {
            $nutrient = $this->slotNutrient($slot, $priorities);
            $food = Food::query()
                ->whereNotIn('id', $usedIds)
                ->where($nutrient, '>', 0)
                ->orderBy($nutrient, 'desc')
                ->orderBy('recipe_loves', 'desc')
                ->first();

            if (!$food) {
                $food = Food::query()
                    ->whereNotIn('id', $usedIds)
                    ->orderBy('protein', 'desc')
                    ->first();
            }

            if (!$food) {
                continue;
            }

            $usedIds[] = $food->id;

            $meals[] = [
                'slot' => $slot,
                'focus_nutrient' => $nutrient,
                'food' => $this->foodToArray($food),
            ];
        }

        return [
            'targets' => $targets,
            'meals'   => $meals,
            'notes'   => [
                'Utamakan variasi sumber protein hewani dan nabati.',
                'Gunakan metode masak rebus, kukus, atau tumis ringan untuk menjaga nutrisi.',
            ],
        ];
    }

    public function createWeeklyMealPlan(StuntingPrediction $prediction, int $userId, int $days = 7): MealPlan
    {
        $days = max(1, min(14, $days));
        $shapFactors = $this->extractShapFactors($prediction);
        $priorities = $this->determineNutrientPriorities($shapFactors);
        $targets = $this->resolveTargets($prediction->risk_label ?? 'low_risk');

        return DB::transaction(function () use ($prediction, $userId, $days, $priorities, $targets) {
            MealPlan::where('user_id', $userId)->where('is_active', true)->update(['is_active' => false]);

            $start = Carbon::today();
            $end = (clone $start)->addDays($days - 1);

            $mealPlan = MealPlan::create([
                'user_id' => $userId,
                'stunting_prediction_id' => $prediction->id,
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'targets' => $targets,
                'notes' => [
                    'Variasikan sumber protein hewani dan nabati setiap hari.',
                    'Batasi pengulangan menu yang sama agar user tidak bosan.',
                ],
                'is_active' => true,
            ]);

            $usedFoodIds = [];
            for ($dayIndex = 0; $dayIndex < $days; $dayIndex++) {
                foreach (self::MEAL_SLOTS as $slot) {
                    $nutrient = $this->slotNutrient($slot, $priorities);
                    $food = $this->pickFoodForSlot($nutrient, $usedFoodIds);

                    if (!$food) {
                        continue;
                    }

                    $usedFoodIds[] = $food->id;

                    MealPlanItem::create([
                        'meal_plan_id' => $mealPlan->id,
                        'food_id' => $food->id,
                        'day_index' => $dayIndex,
                        'slot' => $slot,
                        'focus_nutrient' => $nutrient,
                        'food_snapshot' => $this->foodToArray($food),
                    ]);
                }
            }

            return $mealPlan->load('items');
        });
    }

    public function getActiveMealPlan(int $userId): ?MealPlan
    {
        return MealPlan::query()
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->whereDate('end_date', '>=', Carbon::today()->toDateString())
            ->latest('id')
            ->with('items')
            ->first();
    }

    public function getMealPlanHistory(int $userId, int $limit = 10)
    {
        $limit = max(1, min(50, $limit));

        return MealPlan::query()
            ->where('user_id', $userId)
            ->with('items')
            ->orderByDesc('id')
            ->paginate($limit);
    }

    public function refreshPlanDay(MealPlan $mealPlan, int $dayIndex): MealPlan
    {
        $dayIndex = max(0, min(13, $dayIndex));
        $prediction = $mealPlan->prediction;
        $priorities = $this->determineNutrientPriorities($this->extractShapFactors($prediction));

        return DB::transaction(function () use ($mealPlan, $dayIndex, $priorities) {
            $keepFoodIds = $mealPlan->items()
                ->where('day_index', '!=', $dayIndex)
                ->pluck('food_id')
                ->filter()
                ->values()
                ->all();

            $mealPlan->items()->where('day_index', $dayIndex)->delete();

            foreach (self::MEAL_SLOTS as $slot) {
                $nutrient = $this->slotNutrient($slot, $priorities);
                $food = $this->pickFoodForSlot($nutrient, $keepFoodIds);

                if (!$food) {
                    continue;
                }

                $keepFoodIds[] = $food->id;

                MealPlanItem::create([
                    'meal_plan_id' => $mealPlan->id,
                    'food_id' => $food->id,
                    'day_index' => $dayIndex,
                    'slot' => $slot,
                    'focus_nutrient' => $nutrient,
                    'food_snapshot' => $this->foodToArray($food),
                ]);
            }

            return $mealPlan->load('items');
        });
    }

    public function formatMealPlan(MealPlan $mealPlan): array
    {
        $days = [];
        foreach ($mealPlan->items as $item) {
            $days[$item->day_index][] = [
                'item_id' => $item->id,
                'slot' => $item->slot,
                'focus_nutrient' => $item->focus_nutrient,
                'food' => $item->food_snapshot,
                'is_completed' => $item->is_completed,
                'completed_at' => optional($item->completed_at)->toIso8601String(),
            ];
        }

        ksort($days);

        return [
            'id' => $mealPlan->id,
            'prediction_id' => $mealPlan->stunting_prediction_id,
            'start_date' => optional($mealPlan->start_date)->toDateString(),
            'end_date' => optional($mealPlan->end_date)->toDateString(),
            'targets' => $mealPlan->targets,
            'notes' => $mealPlan->notes,
            'is_active' => $mealPlan->is_active,
            'completion_summary' => [
                'total_items' => (int) $mealPlan->items->count(),
                'completed_items' => (int) $mealPlan->items->where('is_completed', true)->count(),
            ],
            'days' => array_map(fn ($meals, $index) => [
                'day_index' => (int) $index,
                'meals' => $meals,
            ], $days, array_keys($days)),
        ];
    }

    public function updateMealItemCompletion(MealPlanItem $item, bool $isCompleted): MealPlanItem
    {
        $item->is_completed = $isCompleted;
        $item->completed_at = $isCompleted ? now() : null;
        $item->save();

        return $item;
    }

    public function buildDailyProgress(MealPlan $mealPlan): array
    {
        $progress = [];

        foreach ($mealPlan->items->groupBy('day_index') as $dayIndex => $items) {
            $total = $items->count();
            $completed = $items->where('is_completed', true)->count();
            $percentage = $total > 0 ? (int) round(($completed / $total) * 100) : 0;

            $progress[] = [
                'day_index' => (int) $dayIndex,
                'total_items' => $total,
                'completed_items' => $completed,
                'completion_percentage' => $percentage,
                'is_day_completed' => $total > 0 && $completed === $total,
            ];
        }

        usort($progress, fn ($a, $b) => $a['day_index'] <=> $b['day_index']);

        return $progress;
    }

    /**
     * Extract SHAP top_factors from the prediction explanation.
     */
    public function extractShapFactors(StuntingPrediction $prediction): array
    {
        $explanation = $prediction->explanation;

        if (empty($explanation) || !isset($explanation['top_factors'])) {
            return [];
        }

        return array_filter($explanation['top_factors'], function ($factor) {
            return isset($factor['feature']) && ($factor['impact'] ?? '') === 'increase_risk';
        });
    }

    /**
     * Map SHAP factors to ordered nutrient priorities.
     */
    public function determineNutrientPriorities(array $shapFactors): array
    {
        $priorities = [];

        foreach ($shapFactors as $factor) {
            $feature = $factor['feature'] ?? '';
            $mapped  = self::FACTOR_NUTRIENT_MAP[$feature] ?? ['protein', 'calories'];

            foreach ($mapped as $nutrient) {
                if (!in_array($nutrient, $priorities)) {
                    $priorities[] = $nutrient;
                }
            }
        }

        // Default fallback
        if (empty($priorities)) {
            $priorities = ['protein', 'calories'];
        }

        return $priorities;
    }

    /**
     * Build a human-readable reason string from SHAP factors.
     */
    private function buildReason(array $shapFactors): string
    {
        if (empty($shapFactors)) {
            return 'Makanan bergizi untuk mendukung nutrisi ibu hamil';
        }

        $firstFactor = $shapFactors[0]['feature'] ?? '';

        return self::FACTOR_REASONS[$firstFactor]
            ?? 'Makanan bergizi untuk mendukung nutrisi ibu hamil';
    }

    /**
     * Get human-readable labels for SHAP factors.
     */
    public function getFactorLabels(array $shapFactors): array
    {
        $labels = [];

        foreach ($shapFactors as $factor) {
            $feature = $factor['feature'] ?? '';
            $labels[] = self::FACTOR_LABELS[$feature] ?? $feature;
        }

        return $labels;
    }

    private function pickDiverseFoods(array $priorities, int $limit, ?UserFoodPreference $preference = null)
    {
        $selected = collect();
        $usedCategories = [];
        $excludeIds = [];

        // Preference filters
        $excludedCategories = collect($preference?->excluded_categories ?? [])->map(fn ($c) => mb_strtolower(trim($c)))->filter()->values()->all();
        $preferredCategories = collect($preference?->preferred_categories ?? [])->map(fn ($c) => mb_strtolower(trim($c)))->filter()->values()->all();
        $avoidSpicy = (bool) ($preference?->avoid_spicy ?? false);
        $excludedKeywords = collect($preference?->excluded_keywords ?? [])->map(fn ($k) => mb_strtolower(trim($k)))->filter()->values()->all();

        foreach ($priorities as $nutrient) {
            if ($selected->count() >= $limit) {
                break;
            }

            $candidateQuery = Food::query()
                ->whereNotIn('id', $selected->pluck('id')->all())
                ->whereNotIn('id', $excludeIds)
                ->where($nutrient, '>', 0)
                ->when(!empty($usedCategories), fn ($q) => $q->whereNotIn('category', $usedCategories))
                ->when(!empty($excludedCategories), fn ($q) => $q->whereRaw('LOWER(category) NOT IN (' . implode(',', array_fill(0, count($excludedCategories), '?')) . ')', $excludedCategories))
                ->when(!empty($preferredCategories), fn ($q) => $q->whereRaw('LOWER(category) IN (' . implode(',', array_fill(0, count($preferredCategories), '?')) . ')', $preferredCategories))
                ->orderBy($nutrient, 'desc')
                ->orderBy('recipe_loves', 'desc')
                ->limit(30);

            // Avoid spicy heuristic on name
            if ($avoidSpicy) {
                $candidateQuery->where('name', 'not like', '%pedas%')
                    ->where('name', 'not like', '%cabe%')
                    ->where('name', 'not like', '%sambal%');
            }

            foreach ($excludedKeywords as $keyword) {
                $candidateQuery->where('name', 'not like', '%' . $keyword . '%');
            }

            // Randomize within top N for variety
            $candidate = $candidateQuery->inRandomOrder()->first();

            if ($candidate) {
                $selected->push($candidate);
                if (!empty($candidate->category)) {
                    $usedCategories[] = $candidate->category;
                }
            }
        }

        if ($selected->count() < $limit) {
            $fallback = Food::query()
                ->whereNotIn('id', $selected->pluck('id')->all())
                ->orderBy('protein', 'desc')
                ->limit($limit - $selected->count())
                ->get();

            $selected = $selected->concat($fallback);
        }

        return $selected->take($limit);
    }

    private function slotNutrient(string $slot, array $priorities): string
    {
        return match ($slot) {
            'breakfast' => $priorities[0] ?? 'calories',
            'lunch'     => $priorities[1] ?? $priorities[0] ?? 'protein',
            'dinner'    => $priorities[2] ?? 'protein',
            default     => 'calories',
        };
    }

    private function resolveTargets(string $riskLabel): array
    {
        return $riskLabel === 'high_risk'
            ? ['calories' => 2200, 'protein' => 75, 'iron' => 27, 'calcium' => 1000]
            : ['calories' => 2000, 'protein' => 65, 'iron' => 24, 'calcium' => 900];
    }

    private function pickFoodForSlot(string $nutrient, array $excludeIds): ?Food
    {
        $query = Food::query()->whereNotIn('id', $excludeIds);

        $food = (clone $query)
            ->where($nutrient, '>', 0)
            ->orderBy($nutrient, 'desc')
            ->orderBy('recipe_loves', 'desc')
            ->first();

        if ($food) {
            return $food;
        }

        return $query->orderBy('protein', 'desc')->first();
    }

    private function foodToArray(Food $food): array
    {
        return [
            'id'            => $food->id,
            'name'          => $food->name,
            'category'      => $food->category,
            'image_url'     => $food->image_url,
            'protein'       => $food->protein,
            'calories'      => $food->calories,
            'fat'           => $food->fat,
            'carbohydrates' => $food->carbohydrates,
            'iron'          => $food->iron,
            'calcium'       => $food->calcium,
            'has_recipe'    => !empty($food->steps) || !empty($food->ingredients),
        ];
    }
}
