<?php

namespace App\Services;

use App\Models\Food;
use App\Models\StuntingPrediction;
use Illuminate\Support\Facades\Log;

class FoodRecommendationService
{
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
    public function recommend(StuntingPrediction $prediction, int $limit = 5): array
    {
        $shapFactors = $this->extractShapFactors($prediction);
        $priorities  = $this->determineNutrientPriorities($shapFactors);
        $primarySort = $priorities[0] ?? 'protein';

        // Query foods sorted by the top nutrient priority
        $foods = Food::orderByNutrient($primarySort, 'desc')
            ->where($primarySort, '>', 0)
            ->limit($limit)
            ->get();

        $reason = $this->buildReason($shapFactors);

        $formattedFoods = $foods->map(fn (Food $food) => [
            'id'        => $food->id,
            'name'      => $food->name,
            'image_url' => $food->image_url,
            'protein'   => $food->protein,
            'calories'  => $food->calories,
            'fat'       => $food->fat,
            'carbohydrates' => $food->carbohydrates,
            'reason'    => $reason,
        ])->toArray();

        return [
            'foods'              => $formattedFoods,
            'nutrient_priorities' => $priorities,
            'factor_labels'      => $this->getFactorLabels($shapFactors),
        ];
    }

    /**
     * Generate lightweight recommendations for low-risk predictions.
     */
    public function recommendLowRisk(int $limit = 3): array
    {
        $foods = Food::orderByNutrient('protein', 'desc')
            ->where('protein', '>', 0)
            ->limit($limit)
            ->get();

        return $foods->map(fn (Food $food) => [
            'id'        => $food->id,
            'name'      => $food->name,
            'image_url' => $food->image_url,
            'protein'   => $food->protein,
            'calories'  => $food->calories,
            'fat'       => $food->fat,
            'carbohydrates' => $food->carbohydrates,
            'reason'    => 'Makanan bergizi untuk mendukung kehamilan sehat',
        ])->toArray();
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
}
