<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MealPlan extends Model
{
    protected $fillable = [
        'user_id',
        'stunting_prediction_id',
        'start_date',
        'end_date',
        'targets',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'targets' => 'array',
        'notes' => 'array',
        'is_active' => 'boolean',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function prediction(): BelongsTo
    {
        return $this->belongsTo(StuntingPrediction::class, 'stunting_prediction_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(MealPlanItem::class)->orderBy('day_index')->orderBy('slot');
    }
}
