<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MealPlan;
use App\Models\MealPlanItem;
use App\Models\ScrapedFood;

use App\Models\StuntingPrediction;

class MealPlanController extends Controller
{
    /**
     * Get the user's active meal plan.
     */
    public function getActiveMealPlan(Request $request)
    {
        $user = $request->user();
        
        $mealPlan = MealPlan::with('items')
            ->where('user_id', $user->user_id)
            ->where('is_active', true)
            ->first();

        if (!$mealPlan) {
            return response()->json([
                'success' => true,
                'message' => 'Belum ada meal plan aktif',
                'data' => null
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $mealPlan
        ]);
    }

    /**
     * Create a new meal plan (daily or weekly) and auto-fill it smartly.
     */
    public function generateMealPlan(Request $request)
    {
        $user = $request->user();
        $validated = $request->validate([
            'type' => 'required|in:daily,weekly',
            'max_price' => 'nullable|numeric|min:0'
        ]);

        $days = $validated['type'] === 'weekly' ? 7 : 1;
        
        // Deactivate previous plans
        MealPlan::where('user_id', $user->user_id)->update(['is_active' => false]);
        
        $mealPlan = MealPlan::create([
            'user_id' => $user->user_id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays($days - 1)->toDateString(),
            'is_active' => true,
            'notes' => 'Generated ' . $validated['type'] . ' meal plan',
        ]);

        // Auto-generate items based on maternal needs and budget
        $query = ScrapedFood::query();
        if (isset($validated['max_price'])) {
            $query->where(function($q) use ($validated['max_price']) {
                $q->where('price_estimate', '<=', $validated['max_price'])
                  ->orWhereNull('price_estimate');
            });
        }

        // Smart Mapping Logic
        $latestPrediction = StuntingPrediction::where('user_id', $user->user_id)->latest()->first();
        if ($latestPrediction && isset($latestPrediction->input_data)) {
            $input = $latestPrediction->input_data;
            $hb = isset($input['maternal_hemoglobin']) ? (float)$input['maternal_hemoglobin'] : 12.0;
            $weight = isset($input['mother_weight']) ? (float)$input['mother_weight'] : 55.0;

            $priorityKeywords = [];
            if ($hb < 11.0) {
                $priorityKeywords = array_merge($priorityKeywords, ['bayam', 'hati', 'daging', 'sapi', 'ayam', 'telur']);
            }
            if ($weight < 50.0) {
                $priorityKeywords = array_merge($priorityKeywords, ['alpukat', 'susu', 'keju', 'kacang', 'telur', 'daging']);
            }

            if (!empty($priorityKeywords)) {
                $likes = [];
                foreach ($priorityKeywords as $word) {
                    $likes[] = "name LIKE '%{$word}%'";
                }
                $orderRaw = "CASE WHEN (" . implode(" OR ", $likes) . ") THEN 1 ELSE 0 END DESC";
                $query->orderByRaw($orderRaw);
            }
        }

        $availableFoods = $query->inRandomOrder()->take($days * 4)->get();
        if ($availableFoods->isNotEmpty()) {
            $slots = ['breakfast', 'lunch', 'dinner', 'snack'];
            for ($day = 0; $day < $days; $day++) {
                foreach ($slots as $slot) {
                    $food = $availableFoods->random();
                    MealPlanItem::create([
                        'meal_plan_id' => $mealPlan->id,
                        'day_index' => $day,
                        'slot' => $slot,
                        'scraped_food_id' => $food->id,
                        'food_snapshot' => json_encode($food->toArray()),
                        'focus_nutrient' => $food->category,
                    ]);
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Meal plan cerdas berhasil dibuat',
            'data' => $mealPlan->load('items')
        ]);
    }

    /**
     * Save selected foods to the meal plan.
     */
    public function saveMealPlanItems(Request $request, $mealPlanId)
    {
        $user = $request->user();
        
        $mealPlan = MealPlan::where('id', $mealPlanId)
            ->where('user_id', $user->user_id)
            ->firstOrFail();

        $validated = $request->validate([
            'items' => 'required|array',
            'items.*.day_index' => 'required|integer|min:0|max:6',
            'items.*.slot' => 'required|string|in:breakfast,lunch,dinner,snack',
            'items.*.scraped_food_id' => 'required|exists:scraped_foods,id',
        ]);

        foreach ($validated['items'] as $itemData) {
            $food = ScrapedFood::find($itemData['scraped_food_id']);
            
            MealPlanItem::updateOrCreate(
                [
                    'meal_plan_id' => $mealPlan->id,
                    'day_index' => $itemData['day_index'],
                    'slot' => $itemData['slot']
                ],
                [
                    'scraped_food_id' => $food->id,
                    'food_snapshot' => json_encode($food->toArray()),
                    'focus_nutrient' => $food->category, // Use category as a simple focus nutrient equivalent
                ]
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Item meal plan berhasil disimpan'
        ]);
    }
}
