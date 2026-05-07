<?php

namespace App\Http\Controllers;

use App\Models\UserFoodPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserFoodPreferenceController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $pref = UserFoodPreference::firstOrCreate(['user_id' => $user->user_id]);

        return response()->json([
            'success' => true,
            'data' => $pref,
        ]);
    }

    public function upsert(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'budget_level' => 'nullable|in:low,mid,high',
            'preferred_categories' => 'nullable|array|max:30',
            'preferred_categories.*' => 'string|max:100',
            'excluded_categories' => 'nullable|array|max:30',
            'excluded_categories.*' => 'string|max:100',
            'excluded_keywords' => 'nullable|array|max:30',
            'excluded_keywords.*' => 'string|max:50',
            'allergies' => 'nullable|array|max:20',
            'allergies.*' => 'string|max:50',
            'diet_style' => 'nullable|in:omnivore,vegetarian,pescatarian',
            'avoid_spicy' => 'nullable|boolean',
            'notes' => 'nullable|string|max:500',
        ]);

        $pref = UserFoodPreference::updateOrCreate(
            ['user_id' => $user->user_id],
            $validated
        );

        return response()->json([
            'success' => true,
            'message' => 'Preferensi makanan berhasil disimpan.',
            'data' => $pref,
        ]);
    }
}
