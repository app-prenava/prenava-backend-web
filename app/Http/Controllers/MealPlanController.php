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

        // Calculate accurate nutritional needs
        $bb = $validated['berat_badan'] ?? 60;
        $tb = $validated['tinggi_badan'] ?? 155;
        $trimester = $validated['trimester'] ?? 1;
        $usia = 25; // Default age

        // Mifflin-St Jeor Equation for women
        $bmr = (10 * $bb) + (6.25 * $tb) - (5 * $usia) - 161;
        $tee = $bmr * 1.2; // Sedentary

        // Additional calories based on trimester
        if ($trimester == 2) {
            $tee += 300;
        } elseif ($trimester == 3) {
            $tee += 450;
        }

        // Protein calculation (1.1g per kg + extra for pregnancy)
        $protein = $bb * 1.1;
        if ($trimester >= 2) {
            $protein += 15;
        }

        $validated['kebutuhan_kalori_harian_tepat'] = round($tee) . " kcal";
        $validated['kebutuhan_protein_harian_tepat'] = round($protein) . " gram";

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
