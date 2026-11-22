<?php

namespace Tests\Unit;

use App\Models\ScreeningCategory;
use App\Models\ScreeningQuestion;
use App\Services\ScreeningScoringService;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class ScreeningScoringServiceTest extends TestCase
{
    private ScreeningScoringService $service;
    private Collection $questions;
    private Collection $categories;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ScreeningScoringService();
        $this->questions = $this->makeQuestions();
        $this->categories = $this->makeCategories();
    }

    public function test_all_dimensions_at_or_above_60_returns_balanced(): void
    {
        // All answers = 3 -> (9 - 3) / 9 * 100 = 67 >= 60
        $answers = $this->buildAnswers(3, 3, 3, 3);
        $result = $this->service->evaluate($answers, $this->questions, $this->categories);

        $this->assertSame('balanced', $result['primary_category_slug']);
        $this->assertSame([], $result['secondary_categories']);
        $this->assertSame(67, $result['dimension_scores']['literacy']);
        $this->assertCount(4, $result['recommended_features']);
        $this->assertSame('kalkulator_hpl', $result['recommended_features'][0]['feature_slug']);
    }

    public function test_low_literacy_with_nutrition_secondary_blends_fourth_slot(): void
    {
        // literacy: 1,2,1 => 4 => round((4-3)/9*100) = 11
        // nutrition: 2,2,3 => 7 => round((7-3)/9*100) = 44
        // mental: 3,3,4 => 10 => 78
        // anc: 3,3,3 => 9 => 67
        $answers = [
            ['question_id' => 1, 'selected_value' => '1'],
            ['question_id' => 2, 'selected_value' => '2'],
            ['question_id' => 3, 'selected_value' => '1'],
            ['question_id' => 4, 'selected_value' => '2'],
            ['question_id' => 5, 'selected_value' => '2'],
            ['question_id' => 6, 'selected_value' => '3'],
            ['question_id' => 7, 'selected_value' => '3'],
            ['question_id' => 8, 'selected_value' => '3'],
            ['question_id' => 9, 'selected_value' => '4'],
            ['question_id' => 10, 'selected_value' => '3'],
            ['question_id' => 11, 'selected_value' => '3'],
            ['question_id' => 12, 'selected_value' => '3'],
        ];

        $result = $this->service->evaluate($answers, $this->questions, $this->categories);

        $this->assertSame(11, $result['dimension_scores']['literacy']);
        $this->assertSame(44, $result['dimension_scores']['nutrition']);
        $this->assertSame(78, $result['dimension_scores']['mental']);
        $this->assertSame(67, $result['dimension_scores']['anc']);
        $this->assertSame('low_literacy', $result['primary_category_slug']);
        $this->assertSame(['nutrition_risk'], $result['secondary_categories']);

        $slugs = array_column($result['recommended_features'], 'feature_slug');
        $this->assertSame(['tips', 'local_wisdom', 'kalkulator_hpl', 'anemia'], $slugs);
    }

    public function test_tie_breaker_prioritizes_mental_over_nutrition_anc_literacy(): void
    {
        // All 4 dimensions equal = 33 (< 60)
        $answers = $this->buildAnswers(2, 2, 2, 2);
        $result = $this->service->evaluate($answers, $this->questions, $this->categories);

        $this->assertSame('mental_support', $result['primary_category_slug']);
        $this->assertSame(['nutrition_risk', 'anc_monitoring', 'low_literacy'], $result['secondary_categories']);
    }

    public function test_anc_monitoring_selected_when_lowest(): void
    {
        $answers = $this->buildAnswers(4, 4, 4, 1);
        $result = $this->service->evaluate($answers, $this->questions, $this->categories);

        $this->assertSame('anc_monitoring', $result['primary_category_slug']);
        $this->assertSame(0, $result['dimension_scores']['anc']);
        $this->assertSame([], $result['secondary_categories']);
    }

    public function test_nutrition_risk_selected_when_lowest(): void
    {
        $answers = $this->buildAnswers(4, 1, 4, 4);
        $result = $this->service->evaluate($answers, $this->questions, $this->categories);

        $this->assertSame('nutrition_risk', $result['primary_category_slug']);
        $this->assertSame(0, $result['dimension_scores']['nutrition']);
    }

    private function buildAnswers(int $lit, int $nut, int $men, int $anc): array
    {
        return [
            ['question_id' => 1, 'selected_value' => (string) $lit],
            ['question_id' => 2, 'selected_value' => (string) $lit],
            ['question_id' => 3, 'selected_value' => (string) $lit],
            ['question_id' => 4, 'selected_value' => (string) $nut],
            ['question_id' => 5, 'selected_value' => (string) $nut],
            ['question_id' => 6, 'selected_value' => (string) $nut],
            ['question_id' => 7, 'selected_value' => (string) $men],
            ['question_id' => 8, 'selected_value' => (string) $men],
            ['question_id' => 9, 'selected_value' => (string) $men],
            ['question_id' => 10, 'selected_value' => (string) $anc],
            ['question_id' => 11, 'selected_value' => (string) $anc],
            ['question_id' => 12, 'selected_value' => (string) $anc],
        ];
    }

    private function makeQuestions(): Collection
    {
        $dims = [
            1 => 'literacy', 2 => 'literacy', 3 => 'literacy',
            4 => 'nutrition', 5 => 'nutrition', 6 => 'nutrition',
            7 => 'mental', 8 => 'mental', 9 => 'mental',
            10 => 'anc', 11 => 'anc', 12 => 'anc',
        ];
        $opts = [
            ['value' => '1', 'label' => '1', 'score' => 1],
            ['value' => '2', 'label' => '2', 'score' => 2],
            ['value' => '3', 'label' => '3', 'score' => 3],
            ['value' => '4', 'label' => '4', 'score' => 4],
        ];

        $items = [];
        foreach ($dims as $id => $dim) {
            $q = new ScreeningQuestion();
            $q->id = $id;
            $q->dimension = $dim;
            $q->weight = 1;
            $q->options_json = $opts;
            $items[] = $q;
        }
        return collect($items);
    }

    private function makeCategories(): Collection
    {
        $defs = [
            'low_literacy'   => ['tips', 'local_wisdom', 'kalkulator_hpl', 'komunitas'],
            'nutrition_risk' => ['anemia', 'stunting', 'hidrasi', 'olahraga'],
            'mental_support' => ['depresi', 'komunitas', 'olahraga', 'tips'],
            'anc_monitoring' => ['appointment', 'kunjungan', 'bidan_map', 'kalkulator_hpl'],
            'balanced'       => ['kalkulator_hpl', 'hidrasi', 'olahraga', 'stunting'],
        ];

        $map = [];
        foreach ($defs as $slug => $features) {
            $cat = new ScreeningCategory();
            $cat->slug = $slug;
            $cat->recommended_features_json = array_map(
                fn ($f, $i) => ['feature_slug' => $f, 'title' => $f, 'reason' => 'r', 'route' => "/$f", 'priority' => $i + 1],
                $features,
                array_keys($features)
            );
            $map[$slug] = $cat;
        }
        return collect($map);
    }
}
