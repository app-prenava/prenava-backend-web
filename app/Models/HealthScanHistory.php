<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HealthScanHistory extends Model
{
    protected $table = 'health_scan_histories';

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
