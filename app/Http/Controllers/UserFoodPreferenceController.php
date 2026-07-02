<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\UserFoodPreference;

class UserFoodPreferenceController extends Controller
{
    public function getPreference(Request $request)
    {
        $user = $request->user();
        $preference = UserFoodPreference::firstOrCreate(
            ['user_id' => $user->user_id],
            [
                'allergies' => [],
                'excluded_keywords' => [],
                'excluded_categories' => [],
                'avoid_spicy' => false,
                'target_calories' => 2000,
            ]
        );

        return response()->json([
            'success' => true,
            'data' => $preference
        ]);
    }

    public function savePreference(Request $request)
    {
        $user = $request->user();
        $validated = $request->validate([
            'allergies' => 'nullable|array',
            'excluded_keywords' => 'nullable|array',
            'excluded_categories' => 'nullable|array',
            'avoid_spicy' => 'nullable|boolean',
            'target_calories' => 'nullable|numeric',
        ]);

        $preference = UserFoodPreference::updateOrCreate(
            ['user_id' => $user->user_id],
            [
                'allergies' => $validated['allergies'] ?? [],
                'excluded_keywords' => $validated['excluded_keywords'] ?? [],
                'excluded_categories' => $validated['excluded_categories'] ?? [],
                'avoid_spicy' => $validated['avoid_spicy'] ?? false,
                'target_calories' => $validated['target_calories'] ?? 2000,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Preferensi berhasil disimpan',
            'data' => $preference
        ]);
    }
}
