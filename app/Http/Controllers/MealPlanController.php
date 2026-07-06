<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\GroqService;
use Illuminate\Support\Facades\Log;

class MealPlanController extends Controller
{
    protected GroqService $groqService;

    public function __construct(GroqService $groqService)
    {
        $this->groqService = $groqService;
    }

    /**
     * Generate dynamic meal plan based on full user profile.
     */
    public function generateMealPlan(Request $request)
    {
        $validated = $request->validate([
            'risiko_stunting' => 'nullable|string',
            'bmi_ibu' => 'nullable|numeric',
            'trimester' => 'nullable|integer',
            'berat_badan' => 'nullable|numeric',
            'tinggi_badan' => 'nullable|numeric',
            'hb' => 'nullable|numeric',
            'budget' => 'nullable|string',
            'pantangan' => 'nullable|string',
            'alergi' => 'nullable|string',
        ]);

        try {
            $evaluation = $this->groqService->generateDynamicMealPlan($validated);

            if (!$evaluation) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal mendapatkan evaluasi gizi dari AI.'
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'Evaluasi gizi berhasil',
                'data' => $evaluation
            ]);

        } catch (\Exception $e) {
            Log::error('Meal Plan Generation Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan internal server saat evaluasi meal plan.'
            ], 500);
        }
    }
}
