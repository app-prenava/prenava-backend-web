<?php

namespace App\Services;

use App\Models\ScreeningCategory;
use App\Models\ScreeningQuestion;
use Illuminate\Support\Collection;

class ScreeningScoringService
{
    public const THRESHOLD = 60;

    /**
     * Clinical priority tie-breaker order (lowest index = highest priority).
     */
    public const TIE_BREAKER_PRIORITY = [
        'mental'    => 1,
        'nutrition' => 2,
        'anc'       => 3,
        'literacy'  => 4,
    ];

    public const DIMENSION_TO_CATEGORY = [
        'literacy'  => 'low_literacy',
        'nutrition' => 'nutrition_risk',
        'mental'    => 'mental_support',
        'anc'       => 'anc_monitoring',
    ];

    /**
     * Evaluate submitted answers and return normalized scores, primary/secondary
     * categories, resolved answers, and prioritized feature recommendations.
     *
     * @param  array<int, array{question_id:int, selected_value:string}>  $submittedAnswers
     * @param  Collection<int, ScreeningQuestion>  $questions
     * @param  Collection<string, ScreeningCategory>  $categoriesBySlug
     * @return array{
     *     dimension_scores: array<string, int>,
     *     primary_category_slug: string,
     *     secondary_categories: array<int, string>,
     *     recommended_features: array<int, array<string, mixed>>,
     *     resolved_answers: array<int, array{question_id:int, selected_value:string, score:int}>
     * }
     */
    public function evaluate(
        array $submittedAnswers,
        Collection $questions,
        Collection $categoriesBySlug
    ): array {
        $answersByQuestionId = collect($submittedAnswers)
            ->keyBy(fn ($item) => (int) $item['question_id']);

        $dimensionRaw = [];
        $dimensionMin = [];
        $dimensionMax = [];
        $resolvedAnswers = [];

        foreach ($questions as $question) {
            $submitted = $answersByQuestionId->get($question->id);
            $selectedValue = (string) ($submitted['selected_value'] ?? '');

            $options = collect($question->options_json ?? []);
            $matchedOption = $options->first(
                fn ($opt) => (string) ($opt['value'] ?? '') === $selectedValue
            );

            $score = (int) ($matchedOption['score'] ?? 1);
            $weight = max(1, (int) ($question->weight ?? 1));
            $minScore = (int) ($options->min('score') ?? 1);
            $maxScore = (int) ($options->max('score') ?? 4);

            $dim = $question->dimension;
            $dimensionRaw[$dim] = ($dimensionRaw[$dim] ?? 0) + ($score * $weight);
            $dimensionMin[$dim] = ($dimensionMin[$dim] ?? 0) + ($minScore * $weight);
            $dimensionMax[$dim] = ($dimensionMax[$dim] ?? 0) + ($maxScore * $weight);

            $resolvedAnswers[] = [
                'question_id'    => $question->id,
                'selected_value' => $selectedValue,
                'score'          => $score,
            ];
        }

        // Normalize each dimension readiness score to 0..100
        $dimensionScores = [];
        foreach (array_keys(self::DIMENSION_TO_CATEGORY) as $dim) {
            if (!array_key_exists($dim, $dimensionRaw)) {
                $dimensionScores[$dim] = 100;
                continue;
            }
            $range = max(1, $dimensionMax[$dim] - $dimensionMin[$dim]);
            $normalized = (($dimensionRaw[$dim] - $dimensionMin[$dim]) / $range) * 100;
            $dimensionScores[$dim] = (int) round(max(0, min(100, $normalized)));
        }

        // Find dimensions below threshold (< 60)
        $needsDimensions = [];
        foreach ($dimensionScores as $dim => $score) {
            if ($score < self::THRESHOLD) {
                $needsDimensions[] = [
                    'dimension' => $dim,
                    'score'     => $score,
                    'priority'  => self::TIE_BREAKER_PRIORITY[$dim] ?? 99,
                ];
            }
        }

        if (empty($needsDimensions)) {
            $primarySlug = 'balanced';
            $secondarySlugs = [];
        } else {
            // Sort by lowest readiness score first, then clinical tie-breaker priority
            usort($needsDimensions, function (array $a, array $b) {
                if ($a['score'] !== $b['score']) {
                    return $a['score'] <=> $b['score'];
                }
                return $a['priority'] <=> $b['priority'];
            });

            $primaryDim = $needsDimensions[0]['dimension'];
            $primarySlug = self::DIMENSION_TO_CATEGORY[$primaryDim] ?? 'balanced';

            $secondarySlugs = array_values(array_map(
                fn (array $item) => self::DIMENSION_TO_CATEGORY[$item['dimension']],
                array_slice($needsDimensions, 1)
            ));
        }

        $recommendedFeatures = $this->buildRecommendedFeatures(
            $primarySlug,
            $secondarySlugs,
            $categoriesBySlug
        );

        return [
            'dimension_scores'      => $dimensionScores,
            'primary_category_slug' => $primarySlug,
            'secondary_categories'  => $secondarySlugs,
            'recommended_features'  => $recommendedFeatures,
            'resolved_answers'      => $resolvedAnswers,
        ];
    }

    /**
     * Build 4 prioritized features from the primary category, blending slot #4
     * with the top feature from the first secondary category when applicable.
     */
    private function buildRecommendedFeatures(
        string $primarySlug,
        array $secondarySlugs,
        Collection $categoriesBySlug
    ): array {
        /** @var ScreeningCategory|null $primaryCategory */
        $primaryCategory = $categoriesBySlug->get($primarySlug);
        $baseFeatures = array_values($primaryCategory?->recommended_features_json ?? []);

        if (empty($baseFeatures)) {
            return [];
        }

        // Take up to 4 features sorted by priority
        usort($baseFeatures, fn ($a, $b) => ($a['priority'] ?? 99) <=> ($b['priority'] ?? 99));
        $result = array_slice($baseFeatures, 0, 4);

        // Multi-need blending: replace slot #4 with top feature of first secondary category if unique
        if (!empty($secondarySlugs) && count($result) >= 4) {
            /** @var ScreeningCategory|null $secondaryCategory */
            $secondaryCategory = $categoriesBySlug->get($secondarySlugs[0]);
            $secondaryFeatures = array_values($secondaryCategory?->recommended_features_json ?? []);

            if (!empty($secondaryFeatures)) {
                usort($secondaryFeatures, fn ($a, $b) => ($a['priority'] ?? 99) <=> ($b['priority'] ?? 99));
                $topThreeSlugs = array_map(
                    fn ($f) => $f['feature_slug'] ?? '',
                    array_slice($result, 0, 3)
                );

                foreach ($secondaryFeatures as $candidate) {
                    $slug = $candidate['feature_slug'] ?? '';
                    if ($slug !== '' && !in_array($slug, $topThreeSlugs, true)) {
                        $candidate['priority'] = 4;
                        $candidate['is_secondary_blend'] = true;
                        $result[3] = $candidate;
                        break;
                    }
                }
            }
        }

        // Re-index priorities 1..N cleanly
        return array_values(array_map(function (array $feature, int $idx) {
            $feature['priority'] = $idx + 1;
            return $feature;
        }, $result, array_keys($result)));
    }
}
