<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FoodRecipe extends Model
{
    protected $table = 'food_recipes';

    protected $fillable = [
        'food_id',
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

    public function food(): BelongsTo
    {
        return $this->belongsTo(Food::class);
    }
}
