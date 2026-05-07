<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Food extends Model
{
    protected $table = 'foods';

    protected $fillable = [
        'name',
        'category',
        'protein',
        'iron',
        'calcium',
        'vitamin_a',
        'calories',
        'carbohydrates',
        'fat',
        'image_url',
        'description',
        'ingredients',
        'steps',
        'source_url',
        'recipe_category',
        'recipe_loves',
        'total_ingredients',
        'total_steps',
        'recipe_synced_at',
    ];

    protected $casts = [
        'protein'       => 'float',
        'iron'          => 'float',
        'calcium'       => 'float',
        'vitamin_a'     => 'float',
        'calories'      => 'float',
        'carbohydrates' => 'float',
        'fat'           => 'float',
        'recipe_loves' => 'integer',
        'total_ingredients' => 'integer',
        'total_steps' => 'integer',
        'recipe_synced_at' => 'datetime',
    ];

    // ─── Scopes ───────────────────────────────────────────

    public function scopeHighProtein($query, float $minProtein = 5.0)
    {
        return $query->where('protein', '>=', $minProtein);
    }

    public function scopeOrderByNutrient($query, string $nutrient = 'protein', string $direction = 'desc')
    {
        $allowed = ['protein', 'calories', 'carbohydrates', 'fat', 'iron', 'calcium'];
        $col = in_array($nutrient, $allowed) ? $nutrient : 'protein';

        return $query->orderBy($col, $direction);
    }

    public function scopeLowCalorie($query, float $maxCalories = 200.0)
    {
        return $query->where('calories', '<=', $maxCalories);
    }
}
