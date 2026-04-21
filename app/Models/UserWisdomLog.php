<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserWisdomLog extends Model
{
    protected $table = 'user_wisdom_logs';

    protected $fillable = [
        'user_id',
        'local_wisdom_id',
        'checked_date'
    ];

    protected $casts = [
        'checked_date' => 'date'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function localWisdom(): BelongsTo
    {
        return $this->belongsTo(LocalWisdom::class, 'local_wisdom_id', 'id');
    }
}
