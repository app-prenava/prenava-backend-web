<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MealPlanItem extends Model
{
    protected $fillable = [
        'meal_plan_id',
        'food_id',
        'day_index',
        'slot',
        'focus_nutrient',
        'food_snapshot',
        'is_completed',
        'completed_at',
    ];

    protected $casts = [
        'day_index' => 'integer',
        'food_snapshot' => 'array',
        'is_completed' => 'boolean',
        'completed_at' => 'datetime',
    ];

    public function mealPlan(): BelongsTo
    {
        return $this->belongsTo(MealPlan::class);
    }

    public function food(): BelongsTo
    {
        return $this->belongsTo(Food::class);
    }
}
