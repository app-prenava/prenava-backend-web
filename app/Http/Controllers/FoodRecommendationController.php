<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ScrapedFood;
use App\Models\UserFoodPreference;

class FoodRecommendationController extends Controller
{
    /**
     * Get food recommendations based on user preferences.
     * If user does not have preferences, return standard recommendations.
     */
    public function getRecommendations(Request $request)
    {
        $user = $request->user();
        
        $query = ScrapedFood::query();
        
        // Filter based on preferences if exists
        if ($user) {
            $preference = UserFoodPreference::where('user_id', $user->user_id)->first();
            
            if ($preference) {
                if (!empty($preference->excluded_categories)) {
                    $query->whereNotIn('category', $preference->excluded_categories);
                }
                
                // Exclude allergies/keywords from name
                if (!empty($preference->allergies)) {
                    foreach ($preference->allergies as $allergy) {
                        $query->where('name', 'not like', '%' . $allergy . '%');
                        $query->where('description', 'not like', '%' . $allergy . '%');
                    }
                }

                if (!empty($preference->excluded_keywords)) {
                    foreach ($preference->excluded_keywords as $keyword) {
                        $query->where('name', 'not like', '%' . $keyword . '%');
                    }
                }
                
                if ($preference->avoid_spicy) {
                    $query->where('name', 'not like', '%pedas%')
                          ->where('name', 'not like', '%cabe%')
                          ->where('name', 'not like', '%sambal%')
                          ->where('name', 'not like', '%balado%');
                }
            }
        }
        
        // Let's get diverse categories for better recommendation
        $recommendations = $query->inRandomOrder()->take(10)->get();
        
        return response()->json([
            'success' => true,
            'message' => 'Rekomendasi makanan berhasil diambil',
            'data' => $recommendations
        ]);
    }
}
