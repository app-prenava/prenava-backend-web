<?php

namespace App\Http\Controllers;

use App\Http\Requests\StuntingPredictRequest;
use App\Http\Resources\StuntingPredictionResource;
use App\Services\StuntingPredictionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Exception;

class StuntingPredictionController extends Controller
{
    private StuntingPredictionService $service;

    public function __construct(StuntingPredictionService $service)
    {
        $this->service = $service;
    }

    /**
     * POST /api/stunting/predict
     *
     * Receive humanized input → map → call ML → store → return result.
     */
    public function predict(StuntingPredictRequest $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        try {
            $prediction = $this->service->predict(
                $request->validated(),
                $user->user_id
            );

            return response()->json([
                'success' => true,
                'message' => 'Prediksi stunting berhasil.',
                'data'    => new StuntingPredictionResource($prediction),
            ], 201);

        } catch (Exception $e) {
            $code = in_array($e->getCode(), [502, 504]) ? $e->getCode() : 500;

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $code);
        }
    }

    /**
     * GET /api/stunting/history
     *
     * Paginated prediction history for authenticated user.
     */
    public function history(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        $perPage = (int) $request->query('per_page', 10);
        $perPage = min(max($perPage, 1), 50);

        $paginated = $this->service->getHistory($user->user_id, $perPage);

        return response()->json([
            'success' => true,
            'data'    => StuntingPredictionResource::collection($paginated->items()),
            'meta'    => [
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'per_page'     => $paginated->perPage(),
                'total'        => $paginated->total(),
            ],
        ]);
    }

    /**
     * GET /api/stunting/history/{id}
     *
     * Single prediction detail (ownership-scoped).
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        $prediction = $this->service->getDetail($user->user_id, $id);

        if (!$prediction) {
            return response()->json([
                'success' => false,
                'message' => 'Data prediksi tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => new StuntingPredictionResource($prediction),
        ]);
    }

    /**
     * GET /api/stunting/questions
     *
     * Questionnaire metadata for mobile frontend rendering.
     */
    public function questions(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => StuntingPredictionService::getQuestions(),
        ]);
    }
}
