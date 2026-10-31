<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FoodScan extends Model
{
    use HasFactory;

    protected $table = 'food_scans';
    protected $primaryKey = 'food_scan_id';

    protected $fillable = [
        'user_id',
        'pregnancy_id',
        'image_path',
        'nutrition_score',
        'balance_status',
        'has_iron_protein',
        'has_folate_vegetable',
        'has_fruit',
        'has_carbohydrate',
        'total_items_detected',
        'detected_items',
        'proportion_breakdown',
        'clinical_feedback',
        'actionable_recommendations',
        'coin_rewarded',
        'coin_amount',
    ];

    protected $casts = [
        'nutrition_score'             => 'integer',
        'has_iron_protein'            => 'boolean',
        'has_folate_vegetable'        => 'boolean',
        'has_fruit'                   => 'boolean',
        'has_carbohydrate'            => 'boolean',
        'total_items_detected'        => 'integer',
        'detected_items'              => 'array',
        'proportion_breakdown'        => 'array',
        'actionable_recommendations'  => 'array',
        'coin_rewarded'               => 'boolean',
        'coin_amount'                 => 'decimal:2',
        'created_at'                  => 'datetime',
        'updated_at'                  => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function pregnancy(): BelongsTo
    {
        return $this->belongsTo(Pregnancy::class, 'pregnancy_id', 'pregnancy_id');
    }
}
