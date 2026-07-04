<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ScrapedFood;
use App\Models\UserFoodPreference;
use App\Models\StuntingPrediction;

class FoodRecommendationController extends Controller
{
    /**
     * Get food recommendations based on user preferences, budget, and maternal needs.
     */
    public function getRecommendations(Request $request)
    {
        $user = $request->user();
        
        $query = ScrapedFood::query();

        // 1. Filter by Budget if provided
        if ($request->has('max_price')) {
            $maxPrice = (float) $request->max_price;
            $query->where(function($q) use ($maxPrice) {
                $q->where('price_estimate', '<=', $maxPrice)
                  ->orWhereNull('price_estimate');
            });
        }
        
        // 2. Filter based on preferences if exists
        if ($user) {
            $preference = UserFoodPreference::where('user_id', $user->user_id)->first();
            
            if ($preference) {
                if (!empty($preference->excluded_categories)) {
                    $query->whereNotIn('category', $preference->excluded_categories);
                }
                
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

            // 3. Smart Mapping: Check latest stunting prediction for maternal health condition
            $latestPrediction = StuntingPrediction::where('user_id', $user->user_id)->latest()->first();
            
            if ($latestPrediction && isset($latestPrediction->input_data)) {
                $input = $latestPrediction->input_data;
                $hb = isset($input['maternal_hemoglobin']) ? (float)$input['maternal_hemoglobin'] : 12.0;
                $weight = isset($input['mother_weight']) ? (float)$input['mother_weight'] : 55.0;

                // Priority keywords based on condition
                $priorityKeywords = [];
                if ($hb < 11.0) {
                    // Anemia -> needs iron
                    $priorityKeywords = array_merge($priorityKeywords, ['bayam', 'hati', 'daging', 'sapi', 'ayam', 'telur']);
                }
                if ($weight < 50.0) {
                    // Underweight -> needs calories/fat/protein
                    $priorityKeywords = array_merge($priorityKeywords, ['alpukat', 'susu', 'keju', 'kacang', 'telur', 'daging']);
                }

                if (!empty($priorityKeywords)) {
                    // Boost prioritizing these items using MySQL CASE statement ordering
                    $likes = [];
                    foreach ($priorityKeywords as $word) {
                        $likes[] = "name LIKE '%{$word}%'";
                    }
                    $orderRaw = "CASE WHEN (" . implode(" OR ", $likes) . ") THEN 1 ELSE 0 END DESC";
                    $query->orderByRaw($orderRaw);
                }
            }
        }
        
        $recommendations = $query->inRandomOrder()->take(10)->get();
        
        return response()->json([
            'success' => true,
            'message' => 'Rekomendasi makanan cerdas berhasil diambil',
            'data' => $recommendations
        ]);
    }
}
