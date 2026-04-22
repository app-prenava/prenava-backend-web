<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HealthScanHistory extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'result',
        'image_path',
    ];

    protected $casts = [
        'result' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
