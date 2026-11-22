<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserScreeningSession extends Model
{
    protected $table = 'user_screening_sessions';

    protected $fillable = [
        'user_id',
        'version',
        'dimension_scores_json',
        'primary_category_slug',
        'secondary_categories_json',
        'recommended_features_json',
        'completed_at',
    ];

    protected $casts = [
        'dimension_scores_json' => 'array',
        'secondary_categories_json' => 'array',
        'recommended_features_json' => 'array',
        'completed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function primaryCategory(): BelongsTo
    {
        return $this->belongsTo(ScreeningCategory::class, 'primary_category_slug', 'slug');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(UserScreeningAnswer::class, 'session_id');
    }
}
