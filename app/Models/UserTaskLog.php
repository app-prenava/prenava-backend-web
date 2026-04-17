<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserTaskLog extends Model
{
    protected $table = 'user_task_logs';

    protected $fillable = [
        'user_id',
        'daily_task_id',
        'completed_date'
    ];

    protected $casts = [
        'completed_date' => 'date'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function dailyTask(): BelongsTo
    {
        return $this->belongsTo(DailyTask::class, 'daily_task_id', 'id');
    }
}
