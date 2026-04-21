<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LocalWisdom extends Model
{
    protected $table = 'local_wisdom';

    protected $fillable = [
        'myth',
        'reason',
        'region'
    ];

    public function userLogs(): HasMany
    {
        return $this->hasMany(UserWisdomLog::class, 'local_wisdom_id', 'id');
    }
}
