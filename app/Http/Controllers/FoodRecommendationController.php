<?php

namespace App\Http\Controllers;

use App\Http\Resources\FoodResource;
use App\Models\Food;
use App\Models\FoodRecipe;
use App\Models\MealPlan;
use App\Models\MealPlanItem;
use App\Models\StuntingPrediction;
use App\Models\UserFoodPreference;
use App\Services\FoodRecommendationService;
use App\Services\GeminiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Exception;

class FoodRecommendationController extends Controller
{
    private FoodRecommendationService $recommendationService;
    private GeminiService $geminiService;

    public function __construct(
        FoodRecommendationService $recommendationService,
        GeminiService $geminiService
    ) {
        $this->recommendationService = $recommendationService;
        $this->geminiService         = $geminiService;
    }

    /**
     * GET /api/stunting/recommendations/{prediction_id}
     *
     * Full recommendation flow:
     * 1. Fetch prediction (ownership check)
     * 2. Check cache → return if hit
     * 3. Generate food recommendations (rule-based)
     * 4. Call Gemini AI (educational only)
     * 5. Cache results → return
     */
    public function recommendations(Request $request, int $predictionId): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        // Ownership-scoped fetch
        $prediction = StuntingPrediction::forUser($user->user_id)
            ->where('id', $predictionId)
            ->successful()
            ->first();

        if (!$prediction) {
            return response()->json([
                'success' => false,
                'message' => 'Data prediksi tidak ditemukan.',
            ], 404);
        }

        // CACHE HIT — return stored results (but still personalized is handled by service)
        if ($prediction->hasCachedRecommendations()) {
            return response()->json([
                'success' => true,
                'cached'  => true,
                'data'    => [
                    'prediction_summary' => [
                        'risk_label'  => $prediction->risk_label,
                        'probability' => $prediction->probability,
                    ],
                    'recommended_foods' => $prediction->cached_recommendations,
                    'ai_support'        => $prediction->cached_ai_support,
                    'feature_support'   => $this->buildAiFeatureSupport($prediction->risk_label),
                ],
            ]);
        }

        // CACHE MISS — generate fresh recommendations
        try {
            $isHighRisk = $prediction->risk_label === 'high_risk';
            $preference = UserFoodPreference::where('user_id', $user->user_id)->first();

            // Step 1: Rule-based food recommendations
            if ($isHighRisk) {
                $result = $this->recommendationService->recommend($prediction, 5, $preference);
                $foods  = $result['foods'];
                $labels = $result['factor_labels'];
            } else {
                $foods  = $this->recommendationService->recommendLowRisk(3, $preference);
                $labels = ['Kehamilan sehat'];
            }

            // Step 2: Gemini educational AI (only for high_risk)
            $aiSupport = null;

            if ($isHighRisk && !empty($foods)) {
                $foodNames = array_column($foods, 'name');

                $aiSupport = $this->geminiService->getMealPlan(
                    $prediction->risk_label,
                    $labels,
                    $foodNames
                );
            }

            // Step 3: Cache the results
            $prediction->update([
                'cached_recommendations' => $foods,
                'cached_ai_support'      => $aiSupport,
            ]);

            return response()->json([
                'success' => true,
                'cached'  => false,
                'data'    => [
                    'prediction_summary' => [
                        'risk_label'  => $prediction->risk_label,
                        'probability' => $prediction->probability,
                    ],
                    'recommended_foods' => $foods,
                    'ai_support'        => $aiSupport,
                    'feature_support'   => $this->buildAiFeatureSupport($prediction->risk_label),
                ],
            ]);

        } catch (Exception $e) {
            Log::error('RecommendationController: Generation failed', [
                'prediction_id' => $predictionId,
                'message'       => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal menghasilkan rekomendasi. Silakan coba lagi.',
            ], 500);
        }
    }

    /**
     * GET /api/stunting/foods
     *
     * Paginated food catalog (public).
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->query('per_page', 20), 1), 2000);
        $search  = $request->query('search');

        $query = Food::orderByNutrient('protein', 'desc');

        if ($search) {
            $query->where('name', 'like', "%{$search}%");
        }

        $paginated = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => FoodResource::collection($paginated->items()),
            'meta'    => [
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'per_page'     => $paginated->perPage(),
                'total'        => $paginated->total(),
            ],
        ]);
    }

    /**
     * GET /api/stunting/foods/{id}
     *
     * Single food detail (public).
     */
    public function show(int $id): JsonResponse
    {
        $food = Food::find($id);

        if (!$food) {
            return response()->json([
                'success' => false,
                'message' => 'Makanan tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => new FoodResource($food),
        ]);
    }

    /**
     * POST /api/stunting/gemini/cooking-guide
     *
     * On-demand cooking guide for selected foods.
     */
    public function cookingGuide(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        $request->validate([
            'food_names'   => 'required|array|min:1|max:10',
            'food_names.*' => 'required|string|max:200',
        ]);

        $result = $this->geminiService->getCookingGuide($request->input('food_names'));

        if (!$result) {
            return response()->json([
                'success' => false,
                'message' => 'Panduan memasak tidak tersedia saat ini. Silakan coba lagi.',
            ], 503);
        }

        return response()->json([
            'success' => true,
            'data'    => $result,
        ]);
    }

    /**
     * POST /api/stunting/gemini/meal-plan
     *
     * On-demand meal plan for a prediction.
     */
    public function mealPlan(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        $request->validate([
            'prediction_id' => 'required|integer',
        ]);

        $prediction = StuntingPrediction::forUser($user->user_id)
            ->where('id', $request->input('prediction_id'))
            ->successful()
            ->first();

        if (!$prediction) {
            return response()->json([
                'success' => false,
                'message' => 'Data prediksi tidak ditemukan.',
            ], 404);
        }

        $shapFactors = $this->recommendationService->extractShapFactors($prediction);
        $labels      = $this->recommendationService->getFactorLabels($shapFactors);

        // Get food names from cached recommendations or generate
        $foodNames = [];
        if ($prediction->hasCachedRecommendations()) {
            $foodNames = array_column($prediction->cached_recommendations, 'name');
        }

        if (empty($foodNames)) {
            $foodNames = ['bayam', 'telur', 'ikan', 'tempe', 'nasi'];
        }

        $result = $this->geminiService->getMealPlan(
            $prediction->risk_label,
            $labels ?: ['Kehamilan sehat'],
            $foodNames
        );

        if (!$result) {
            return response()->json([
                'success' => false,
                'message' => 'Rencana makan tidak tersedia saat ini. Silakan coba lagi.',
            ], 503);
        }

        return response()->json([
            'success' => true,
            'data'    => $result,
        ]);
    }

    /**
     * POST /api/stunting/meal-plans/generate
     *
     * Deterministic rule-based daily schedule from prediction factors.
     */
    public function generateDailyPlan(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        $request->validate([
            'prediction_id' => 'required|integer',
        ]);

        $prediction = StuntingPrediction::forUser($user->user_id)
            ->where('id', $request->integer('prediction_id'))
            ->successful()
            ->first();

        if (!$prediction) {
            return response()->json([
                'success' => false,
                'message' => 'Data prediksi tidak ditemukan.',
            ], 404);
        }

        $plan = $this->recommendationService->generateDailyMealPlan($prediction);

        return response()->json([
            'success' => true,
            'data'    => [
                'prediction_summary' => [
                    'risk_label'  => $prediction->risk_label,
                    'probability' => $prediction->probability,
                ],
                'daily_plan' => $plan,
            ],
        ]);
    }

    /**
     * POST /api/stunting/meal-plans
     *
     * Persist a weekly plan (default 7 days) for authenticated user.
     */
    public function createWeeklyPlan(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $request->validate([
            'prediction_id' => 'required|integer',
            'days'          => 'nullable|integer|min:1|max:14',
        ]);

        $prediction = StuntingPrediction::forUser($user->user_id)
            ->where('id', $request->integer('prediction_id'))
            ->successful()
            ->first();

        if (!$prediction) {
            return response()->json([
                'success' => false,
                'message' => 'Data prediksi tidak ditemukan.',
            ], 404);
        }

        $mealPlan = $this->recommendationService->createWeeklyMealPlan(
            $prediction,
            $user->user_id,
            (int) $request->input('days', 7)
        );

        return response()->json([
            'success' => true,
            'data' => $this->recommendationService->formatMealPlan($mealPlan),
        ]);
    }

    /**
     * GET /api/stunting/meal-plans/current
     */
    public function currentPlan(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $mealPlan = $this->recommendationService->getActiveMealPlan($user->user_id);
        if (!$mealPlan) {
            return response()->json([
                'success' => false,
                'message' => 'Meal plan aktif belum tersedia.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->recommendationService->formatMealPlan($mealPlan),
        ]);
    }

    /**
     * POST /api/stunting/meal-plans/{id}/refresh-day
     */
    public function refreshPlanDay(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $request->validate([
            'day_index' => 'required|integer|min:0|max:13',
        ]);

        $mealPlan = MealPlan::where('id', $id)
            ->where('user_id', $user->user_id)
            ->with(['items', 'prediction'])
            ->first();

        if (!$mealPlan) {
            return response()->json([
                'success' => false,
                'message' => 'Meal plan tidak ditemukan.',
            ], 404);
        }

        $updated = $this->recommendationService->refreshPlanDay(
            $mealPlan,
            $request->integer('day_index')
        );

        return response()->json([
            'success' => true,
            'message' => 'Menu harian berhasil diperbarui.',
            'data' => $this->recommendationService->formatMealPlan($updated),
        ]);
    }

    /**
     * GET /api/stunting/meal-plans/history
     */
    public function planHistory(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $perPage = (int) $request->query('per_page', 10);
        $paginated = $this->recommendationService->getMealPlanHistory($user->user_id, $perPage);

        return response()->json([
            'success' => true,
            'data' => collect($paginated->items())
                ->map(fn (MealPlan $plan) => $this->recommendationService->formatMealPlan($plan))
                ->values(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ]);
    }

    /**
     * POST /api/stunting/meal-plans/items/{item_id}/completion
     */
    public function setMealItemCompletion(Request $request, int $itemId): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $request->validate([
            'is_completed' => 'required|boolean',
        ]);

        $item = MealPlanItem::query()
            ->where('id', $itemId)
            ->whereHas('mealPlan', fn ($q) => $q->where('user_id', $user->user_id))
            ->with('mealPlan.items')
            ->first();

        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'Meal item tidak ditemukan.',
            ], 404);
        }

        $this->recommendationService->updateMealItemCompletion(
            $item,
            (bool) $request->boolean('is_completed')
        );

        $mealPlan = $item->mealPlan->fresh('items');

        return response()->json([
            'success' => true,
            'message' => 'Status meal item berhasil diperbarui.',
            'data' => $this->recommendationService->formatMealPlan($mealPlan),
        ]);
    }

    /**
     * GET /api/stunting/meal-plans/{id}/progress
     */
    public function mealPlanProgress(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $mealPlan = MealPlan::query()
            ->where('id', $id)
            ->where('user_id', $user->user_id)
            ->with('items')
            ->first();

        if (!$mealPlan) {
            return response()->json([
                'success' => false,
                'message' => 'Meal plan tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'meal_plan_id' => $mealPlan->id,
                'is_active' => $mealPlan->is_active,
                'start_date' => optional($mealPlan->start_date)->toDateString(),
                'end_date' => optional($mealPlan->end_date)->toDateString(),
                'overall' => [
                    'total_items' => (int) $mealPlan->items->count(),
                    'completed_items' => (int) $mealPlan->items->where('is_completed', true)->count(),
                ],
                'daily_progress' => $this->recommendationService->buildDailyProgress($mealPlan),
            ],
        ]);
    }

    /**
     * GET /api/stunting/recipes/{food_id}
     *
     * Recipe detail from local dataset sync.
     */
    public function recipe(int $foodId): JsonResponse
    {
        $food = Food::find($foodId);

        if (!$food) {
            return response()->json([
                'success' => false,
                'message' => 'Makanan tidak ditemukan.',
            ], 404);
        }

        // Prefer new canonical recipe table (linked via food_id)
        $cacheKey = "recipes:food:{$foodId}";
        $recipes = Cache::remember($cacheKey, now()->addMinutes(15), function () use ($foodId) {
            return FoodRecipe::query()
                ->where('food_id', $foodId)
                ->orderByDesc('loves')
                ->limit(10)
                ->get([
                    'id',
                    'title',
                    'ingredients',
                    'steps',
                    'loves',
                    'source_url',
                    'category',
                    'total_ingredients',
                    'total_steps',
                ]);
        });

        // Fallback to embedded fields (legacy) if linking not available
        if ($recipes->isEmpty() && empty($food->ingredients) && empty($food->steps)) {
            return response()->json([
                'success' => false,
                'message' => 'Resep untuk makanan ini belum tersedia.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'food_id'    => $food->id,
                'food_name'  => $food->name,
                'food'       => [
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
                    'vitamin_a'     => $food->vitamin_a,
                    'description'   => $food->description,
                ],
                'recipes'    => $recipes,
                'legacy'     => (empty($food->ingredients) && empty($food->steps)) ? null : [
                    'ingredients' => $food->ingredients,
                    'steps'       => $food->steps,
                    'source_url'  => $food->source_url,
                    'category'    => $food->recipe_category,
                    'loves'       => $food->recipe_loves,
                ],
            ],
        ]);
    }

    /**
     * GET /api/stunting/recipes/categories
     *
     * Categories based on receipt dataset (ayam, ikan, dll) + other recipe categories.
     */
    public function recipeCategories(Request $request): JsonResponse
    {
        $cacheKey = 'recipes:categories';
        $categories = Cache::remember($cacheKey, now()->addMinutes(60), function () {
            return FoodRecipe::query()
                ->whereNotNull('category')
                ->selectRaw('category, COUNT(*) as total')
                ->groupBy('category')
                ->orderByDesc('total')
                ->get()
                ->map(fn ($row) => ['category' => $row->category, 'total' => (int) $row->total])
                ->values();
        });

        return response()->json([
            'success' => true,
            'data' => $categories,
        ]);
    }

    /**
     * GET /api/stunting/recipes
     *
     * Paginated recipes list (optimized) with optional filters.
     * query: category, search, per_page
     */
    public function recipesIndex(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->query('per_page', 20), 1), 100);
        $category = $request->query('category');
        $search = $request->query('search');

        // Join foods to expose image + nutrition for FE list cards (fast, select-minimal)
        $query = FoodRecipe::query()
            ->leftJoin('foods', 'foods.id', '=', 'food_recipes.food_id')
            ->select([
                'food_recipes.id as id',
                'food_recipes.food_id as food_id',
                'food_recipes.title as title',
                'food_recipes.loves as loves',
                'food_recipes.source_url as source_url',
                'food_recipes.category as category',
                'food_recipes.total_ingredients as total_ingredients',
                'food_recipes.total_steps as total_steps',
                'foods.name as food_name',
                'foods.image_url as food_image_url',
                'foods.category as food_category',
                'foods.protein as protein',
                'foods.calories as calories',
                'foods.fat as fat',
                'foods.carbohydrates as carbohydrates',
                'foods.iron as iron',
                'foods.calcium as calcium',
            ])
            ->orderByDesc('food_recipes.loves');

        if ($category) {
            $query->where('food_recipes.category', $category);
        }

        if ($search) {
            $query->where('food_recipes.title', 'like', '%' . $search . '%');
        }

        $paginated = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $paginated->items(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ]);
    }

    /**
     * POST /api/stunting/ai/nutrition-paragraph
     *
     * Body:
     * - foods: array of {name, calories, protein, fat, carbohydrates, iron?, calcium?} (max 10)
     * - targets: optional object
     */
    public function nutritionParagraph(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'foods' => 'required|array|min:1|max:10',
            'foods.*.name' => 'required|string|max:200',
            'foods.*.calories' => 'nullable|numeric',
            'foods.*.protein' => 'nullable|numeric',
            'foods.*.fat' => 'nullable|numeric',
            'foods.*.carbohydrates' => 'nullable|numeric',
            'foods.*.iron' => 'nullable|numeric',
            'foods.*.calcium' => 'nullable|numeric',
            'targets' => 'nullable|array',
        ]);

        $cacheKey = 'ai:nutrition:' . md5(json_encode($validated, JSON_UNESCAPED_UNICODE));
        $cached = Cache::get($cacheKey);
        if ($cached) {
            return response()->json(['success' => true, 'cached' => true, 'data' => $cached]);
        }

        $result = $this->geminiService->getNutritionParagraph($validated['foods'], $validated['targets'] ?? null);

        // Fallback: simple deterministic paragraph
        if (!$result || empty($result['paragraph'])) {
            $names = array_column($validated['foods'], 'name');
            $result = [
                'paragraph' => 'Menu ini terdiri dari ' . implode(', ', array_slice($names, 0, 5)) . '. Pilihan makanan ini membantu memenuhi kebutuhan energi dan protein selama kehamilan. Usahakan variasi sumber protein dan sayur-buah agar gizi lebih seimbang.',
            ];
        }

        Cache::put($cacheKey, $result, now()->addMinutes(30));

        return response()->json([
            'success' => true,
            'cached' => false,
            'data' => $result,
        ]);
    }

    /**
     * GET /api/stunting/ai/preference-questions?prediction_id=123
     *
     * Dynamic questions per user (with safe fallback).
     */
    public function preferenceQuestions(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $predictionId = (int) $request->query('prediction_id', 0);
        $prediction = null;
        if ($predictionId > 0) {
            $prediction = StuntingPrediction::forUser($user->user_id)
                ->where('id', $predictionId)
                ->successful()
                ->first();
        }

        $pref = UserFoodPreference::where('user_id', $user->user_id)->first();
        $context = [
            'user_id' => $user->user_id,
            'risk_label' => $prediction?->risk_label,
            'probability' => $prediction?->probability,
            'existing_preference' => $pref ? $pref->toArray() : null,
        ];

        $cacheKey = 'ai:pref_questions:' . md5(json_encode($context, JSON_UNESCAPED_UNICODE));
        $cached = Cache::get($cacheKey);
        if ($cached) {
            return response()->json(['success' => true, 'cached' => true, 'data' => $cached]);
        }

        $result = $this->geminiService->generatePreferenceQuestions($context);

        if (!$result || empty($result['questions'])) {
            $result = [
                'questions' => [
                    [
                        'id' => 'budget_level',
                        'type' => 'single_select',
                        'question' => 'Budget makan harian kamu kira-kira bagaimana?',
                        'options' => [
                            ['value' => 'low', 'label' => 'Hemat'],
                            ['value' => 'mid', 'label' => 'Sedang'],
                            ['value' => 'high', 'label' => 'Fleksibel'],
                        ],
                    ],
                    [
                        'id' => 'avoid_spicy',
                        'type' => 'single_select',
                        'question' => 'Apakah kamu menghindari makanan pedas?',
                        'options' => [
                            ['value' => true, 'label' => 'Ya'],
                            ['value' => false, 'label' => 'Tidak'],
                        ],
                    ],
                    [
                        'id' => 'excluded_keywords',
                        'type' => 'multi_text',
                        'question' => 'Ada bahan atau kata kunci yang ingin dihindari? (contoh: udang, jeroan, santan)',
                        'options' => [],
                    ],
                ],
            ];
        }

        Cache::put($cacheKey, $result, now()->addHours(12));

        return response()->json([
            'success' => true,
            'cached' => false,
            'data' => $result,
        ]);
    }

    private function buildAiFeatureSupport(?string $riskLabel): array
    {
        return [
            'enabled' => true,
            'mode' => 'ai_assisted_with_rule_guardrails',
            'capabilities' => [
                'reranking_recommendations',
                'meal_plan_explanation',
                'recipe_guidance_language_simplification',
            ],
            'fallback_behavior' => 'rule_based_only',
            'context' => $riskLabel === 'high_risk'
                ? 'AI dipakai untuk memperkaya edukasi dan penjelasan prioritas nutrisi.'
                : 'AI dipakai untuk variasi menu sehat dengan prioritas nutrisi seimbang.',
        ];
    }
}
