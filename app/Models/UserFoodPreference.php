<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserFoodPreference extends Model
{
    protected $table = 'user_food_preferences';

    protected $fillable = [
        'user_id',
        'budget_level',
        'preferred_categories',
        'excluded_categories',
        'excluded_keywords',
        'allergies',
        'diet_style',
        'avoid_spicy',
        'notes',
    ];

    protected $casts = [
        'preferred_categories' => 'array',
        'excluded_categories' => 'array',
        'excluded_keywords' => 'array',
        'allergies' => 'array',
        'avoid_spicy' => 'boolean',
    ];
}
