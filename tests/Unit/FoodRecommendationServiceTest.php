<?php

namespace Tests\Unit;

use App\Models\StuntingPrediction;
use App\Services\FoodRecommendationService;
use PHPUnit\Framework\TestCase;

class FoodRecommendationServiceTest extends TestCase
{
    private FoodRecommendationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new FoodRecommendationService();
    }

    /**
     * Helper: mock prediction with SHAP factors.
     */
    private function mockPrediction(array $topFactors = []): StuntingPrediction
    {
        $prediction = new StuntingPrediction();
        $prediction->explanation = [
            'method'      => 'SHAP',
            'top_factors' => $topFactors,
        ];

        return $prediction;
    }

    // ═══ SHAP EXTRACTION ═══

    public function test_extracts_increase_risk_factors_only(): void
    {
        $prediction = $this->mockPrediction([
            ['feature' => 'mother_height_cm',       'impact' => 'increase_risk', 'value' => 148],
            ['feature' => 'improved_sanitation',     'impact' => 'increase_risk', 'value' => 0],
            ['feature' => 'mother_education_level',  'impact' => 'decrease_risk', 'value' => 4],
        ]);

        $factors = $this->service->extractShapFactors($prediction);

        $this->assertCount(2, $factors);
        $this->assertEquals('mother_height_cm', $factors[0]['feature']);
        $this->assertEquals('improved_sanitation', $factors[1]['feature']);
    }

    public function test_returns_empty_for_no_explanation(): void
    {
        $prediction = new StuntingPrediction();
        $prediction->explanation = null;

        $this->assertEmpty($this->service->extractShapFactors($prediction));
    }

    public function test_returns_empty_for_missing_top_factors(): void
    {
        $prediction = new StuntingPrediction();
        $prediction->explanation = ['method' => 'SHAP'];

        $this->assertEmpty($this->service->extractShapFactors($prediction));
    }

    // ═══ NUTRIENT PRIORITIES ═══

    public function test_default_priorities_when_no_factors(): void
    {
        $priorities = $this->service->determineNutrientPriorities([]);

        $this->assertEquals(['protein', 'calories'], $priorities);
    }

    public function test_priorities_from_height_factor(): void
    {
        $factors = [
            ['feature' => 'mother_height_cm', 'impact' => 'increase_risk'],
        ];

        $priorities = $this->service->determineNutrientPriorities($factors);

        $this->assertContains('protein', $priorities);
        $this->assertContains('calories', $priorities);
    }

    public function test_priorities_deduplicate_nutrients(): void
    {
        $factors = [
            ['feature' => 'mother_height_cm',   'impact' => 'increase_risk'],
            ['feature' => 'improved_sanitation', 'impact' => 'increase_risk'],
        ];

        $priorities = $this->service->determineNutrientPriorities($factors);

        $unique = array_unique($priorities);
        $this->assertCount(count($unique), $priorities);
    }

    public function test_teenage_mother_adds_carbohydrates(): void
    {
        $factors = [
            ['feature' => 'is_teenage_mother', 'impact' => 'increase_risk'],
        ];

        $priorities = $this->service->determineNutrientPriorities($factors);

        $this->assertContains('carbohydrates', $priorities);
    }

    // ═══ FACTOR LABELS ═══

    public function test_factor_labels_are_human_readable(): void
    {
        $factors = [
            ['feature' => 'mother_height_cm',   'impact' => 'increase_risk'],
            ['feature' => 'improved_sanitation', 'impact' => 'increase_risk'],
        ];

        $labels = $this->service->getFactorLabels($factors);

        $this->assertEquals('Tinggi badan ibu', $labels[0]);
        $this->assertEquals('Akses sanitasi', $labels[1]);
    }

    public function test_unknown_factor_uses_feature_name(): void
    {
        $factors = [
            ['feature' => 'unknown_feature', 'impact' => 'increase_risk'],
        ];

        $labels = $this->service->getFactorLabels($factors);

        $this->assertEquals('unknown_feature', $labels[0]);
    }

    public function test_empty_factors_returns_empty_labels(): void
    {
        $this->assertEmpty($this->service->getFactorLabels([]));
    }
}
