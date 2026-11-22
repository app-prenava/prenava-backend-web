<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScreeningCategory extends Model
{
    protected $table = 'screening_categories';

    protected $fillable = [
        'slug',
        'internal_name',
        'display_name',
        'description',
        'badge_color',
        'recommended_features_json',
    ];

    protected $casts = [
        'recommended_features_json' => 'array',
    ];

    public function sessions(): HasMany
    {
        return $this->hasMany(UserScreeningSession::class, 'primary_category_slug', 'slug');
    }
}
