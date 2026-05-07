<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FoodRecipe extends Model
{
    protected $table = 'food_recipes';

    protected $fillable = [
        'recipe_hash',
        'title',
        'title_cleaned',
        'ingredients',
        'steps',
        'loves',
        'source_url',
        'category',
        'total_ingredients',
        'total_steps',
        'ingredients_cleaned',
    ];

    protected $casts = [
        'loves' => 'integer',
        'total_ingredients' => 'integer',
        'total_steps' => 'integer',
    ];
}
