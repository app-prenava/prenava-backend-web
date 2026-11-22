<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScreeningQuestion extends Model
{
    protected $table = 'screening_questions';

    protected $fillable = [
        'code',
        'dimension',
        'dimension_label',
        'question_text',
        'options_json',
        'weight',
        'order_no',
        'is_active',
        'version',
    ];

    protected $casts = [
        'options_json' => 'array',
        'weight' => 'integer',
        'order_no' => 'integer',
        'is_active' => 'boolean',
    ];

    public function answers(): HasMany
    {
        return $this->hasMany(UserScreeningAnswer::class, 'question_id');
    }
}
