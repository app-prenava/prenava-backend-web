<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserScreeningAnswer extends Model
{
    protected $table = 'user_screening_answers';

    protected $fillable = [
        'session_id',
        'question_id',
        'selected_value',
        'score',
    ];

    protected $casts = [
        'score' => 'integer',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(UserScreeningSession::class, 'session_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(ScreeningQuestion::class, 'question_id');
    }
}
