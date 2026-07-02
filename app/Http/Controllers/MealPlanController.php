<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MealPlan;
use App\Models\MealPlanItem;
use App\Models\ScrapedFood;

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
     * Create a new meal plan (daily or weekly).
     */
    public function generateMealPlan(Request $request)
    {
        $user = $request->user();
        $validated = $request->validate([
            'type' => 'required|in:daily,weekly',
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

        return response()->json([
            'success' => true,
            'message' => 'Meal plan berhasil dibuat',
            'data' => $mealPlan
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
