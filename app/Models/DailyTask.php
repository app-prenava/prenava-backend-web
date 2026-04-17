<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DailyTask extends Model
{
    protected $table = 'daily_tasks';

    protected $fillable = [
        'title',
        'description',
        'task_type',
        'target_category',
        'points'
    ];

    public function userLogs(): HasMany
    {
        return $this->hasMany(UserTaskLog::class, 'daily_task_id', 'id');
    }
}
