<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StuntingPrediction extends Model
{
    use HasFactory;

    protected $table = 'stunting_predictions';

    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED  = 'failed';
    public const STATUS_TIMEOUT = 'timeout';

    protected $fillable = [
        'user_id',
        'input_data',
        'ml_payload',
        'ml_response',
        'probability',
        'prediction',
        'risk_label',
        'explanation',
        'recommendations',
        'cached_recommendations',
        'cached_ai_support',
        'model_version',
        'latency_ms',
        'status',
    ];

    protected $casts = [
        'input_data'             => 'array',
        'ml_payload'             => 'array',
        'ml_response'            => 'array',
        'explanation'            => 'array',
        'recommendations'        => 'array',
        'cached_recommendations' => 'array',
        'cached_ai_support'      => 'array',
        'probability'            => 'float',
        'prediction'             => 'integer',
        'latency_ms'             => 'integer',
    ];

    protected $hidden = [
        'ml_payload',
        'ml_response',
        'cached_recommendations',
        'cached_ai_support',
    ];

    // ─── Cache Helpers ────────────────────────────────────

    public function hasCachedRecommendations(): bool
    {
        return !empty($this->cached_recommendations);
    }

    // ─── Relationships ────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    // ─── Scopes ───────────────────────────────────────────

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeLatestFirst($query)
    {
        return $query->orderBy('created_at', 'desc');
    }

    public function scopeSuccessful($query)
    {
        return $query->where('status', self::STATUS_SUCCESS);
    }
}
