<?php

namespace App\Http\Controllers;

use App\Http\Resources\FoodResource;
use App\Models\Food;
use App\Models\StuntingPrediction;
use App\Services\FoodRecommendationService;
use App\Services\GeminiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

        // CACHE HIT — return stored results
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
                ],
            ]);
        }

        // CACHE MISS — generate fresh recommendations
        try {
            $isHighRisk = $prediction->risk_label === 'high_risk';

            // Step 1: Rule-based food recommendations
            if ($isHighRisk) {
                $result = $this->recommendationService->recommend($prediction);
                $foods  = $result['foods'];
                $labels = $result['factor_labels'];
            } else {
                $foods  = $this->recommendationService->recommendLowRisk();
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
        $perPage = min(max((int) $request->query('per_page', 20), 1), 100);
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
}
