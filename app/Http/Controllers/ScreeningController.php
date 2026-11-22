<?php

namespace App\Http\Controllers;

use App\Models\ScreeningCategory;
use App\Models\ScreeningQuestion;
use App\Models\UserScreeningAnswer;
use App\Models\UserScreeningSession;
use App\Services\ScreeningScoringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ScreeningController extends Controller
{
    public function __construct(
        private readonly ScreeningScoringService $scoringService
    ) {}

    /**
     * GET /api/user/screening/questions
     * Return active screening questions without exposing option scores.
     */
    public function questions(Request $request): JsonResponse
    {
        $user = auth()->user();
        $version = (string) $request->query('version', 'v1');

        $questions = ScreeningQuestion::where('is_active', true)
            ->where('version', $version)
            ->orderBy('order_no')
            ->get();

        $hasCompleted = UserScreeningSession::where('user_id', $user->user_id)->exists();

        $formatted = $questions->map(function (ScreeningQuestion $q) {
            $options = collect($q->options_json ?? [])->map(fn ($opt) => [
                'value' => (string) ($opt['value'] ?? ''),
                'label' => (string) ($opt['label'] ?? ''),
            ])->values();

            return [
                'id'              => $q->id,
                'code'            => $q->code,
                'dimension'       => $q->dimension,
                'dimension_label' => $q->dimension_label,
                'question_text'   => $q->question_text,
                'order_no'        => $q->order_no,
                'options'         => $options,
            ];
        })->values();

        return response()->json([
            'version'                 => $version,
            'has_completed_screening' => $hasCompleted,
            'total_questions'         => $formatted->count(),
            'questions'               => $formatted,
        ]);
    }

    /**
     * POST /api/user/screening/submit
     * Validate answers, compute dimension scores & category, persist session, sync users.category.
     */
    public function submit(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'version'                  => 'nullable|string|max:16',
            'answers'                  => 'required|array|min:1',
            'answers.*.question_id'    => 'required|integer',
            'answers.*.selected_value' => 'required|string|max:32',
        ]);

        $version = $validated['version'] ?? 'v1';
        $submittedAnswers = $validated['answers'];

        $questions = ScreeningQuestion::where('is_active', true)
            ->where('version', $version)
            ->orderBy('order_no')
            ->get();

        if ($questions->isEmpty()) {
            return response()->json([
                'message' => 'Pertanyaan skrining tidak ditemukan untuk versi ini.',
            ], 404);
        }

        // Validate completeness and valid option values for every active question
        $answersByQId = collect($submittedAnswers)->keyBy(fn ($a) => (int) $a['question_id']);
        $errors = [];

        foreach ($questions as $question) {
            if (!$answersByQId->has($question->id)) {
                $errors['answers'][] = "Pertanyaan {$question->code} wajib dijawab.";
                continue;
            }
            $selectedValue = (string) $answersByQId->get($question->id)['selected_value'];
            $validValues = collect($question->options_json ?? [])
                ->pluck('value')
                ->map(fn ($v) => (string) $v)
                ->all();

            if (!in_array($selectedValue, $validValues, true)) {
                $errors['answers'][] = "Pilihan tidak valid untuk pertanyaan {$question->code}.";
            }
        }

        if (!empty($errors)) {
            throw ValidationException::withMessages($errors);
        }

        $categoriesBySlug = ScreeningCategory::all()->keyBy('slug');
        $evaluation = $this->scoringService->evaluate($submittedAnswers, $questions, $categoriesBySlug);

        $user = auth()->user();
        $now = now();

        $session = DB::transaction(function () use ($user, $version, $evaluation, $now) {
            $session = UserScreeningSession::create([
                'user_id'                   => $user->user_id,
                'version'                   => $version,
                'dimension_scores_json'     => $evaluation['dimension_scores'],
                'primary_category_slug'     => $evaluation['primary_category_slug'],
                'secondary_categories_json' => $evaluation['secondary_categories'],
                'recommended_features_json' => $evaluation['recommended_features'],
                'completed_at'              => $now,
            ]);

            foreach ($evaluation['resolved_answers'] as $answer) {
                UserScreeningAnswer::create([
                    'session_id'     => $session->id,
                    'question_id'    => $answer['question_id'],
                    'selected_value' => $answer['selected_value'],
                    'score'          => $answer['score'],
                ]);
            }

            // Sync users.category for seamless integration with DailyFeatureController
            $user->category = $evaluation['primary_category_slug'];
            $user->save();

            return $session;
        });

        $session->load('primaryCategory');

        return response()->json(
            array_merge(
                ['message' => 'Skrining berhasil disimpan'],
                $this->formatSessionPayload($session, true)
            ),
            201
        );
    }

    /**
     * GET /api/user/screening/latest
     * Used by mobile splash/login/home to check screening completion and personalize HomePage.
     */
    public function latest(): JsonResponse
    {
        $user = auth()->user();

        $session = UserScreeningSession::with('primaryCategory')
            ->where('user_id', $user->user_id)
            ->orderByDesc('completed_at')
            ->orderByDesc('id')
            ->first();

        if (!$session) {
            return response()->json([
                'has_completed_screening' => false,
            ]);
        }

        return response()->json($this->formatSessionPayload($session, true));
    }

    /**
     * GET /api/user/screening/history
     * Return user's historical screening sessions (latest first).
     */
    public function history(): JsonResponse
    {
        $user = auth()->user();

        $sessions = UserScreeningSession::with('primaryCategory')
            ->where('user_id', $user->user_id)
            ->orderByDesc('completed_at')
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        return response()->json([
            'total'    => $sessions->count(),
            'sessions' => $sessions->map(fn (UserScreeningSession $s) => $this->formatSessionPayload($s, true))->values(),
        ]);
    }

    private function formatSessionPayload(UserScreeningSession $session, bool $hasCompleted): array
    {
        $category = $session->primaryCategory;

        return [
            'has_completed_screening' => $hasCompleted,
            'session_id'              => $session->id,
            'version'                 => $session->version,
            'completed_at'            => $session->completed_at?->toIso8601String(),
            'dimension_scores'        => $session->dimension_scores_json,
            'primary_category'        => [
                'slug'         => $session->primary_category_slug,
                'display_name' => $category?->display_name ?? $session->primary_category_slug,
                'description'  => $category?->description ?? '',
                'badge_color'  => $category?->badge_color ?? '#FA6978',
            ],
            'secondary_categories'    => $session->secondary_categories_json ?? [],
            'recommended_features'    => $session->recommended_features_json ?? [],
        ];
    }
}
