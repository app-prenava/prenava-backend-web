<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaterIntake extends Model
{
    use HasFactory;

    protected $table = 'water_intakes';

    protected $fillable = [
        'user_id',
        'glass_count',
        'jumlah_ml',
        'is_target_reached',
        'tanggal',
    ];

    protected $casts = [
        'glass_count' => 'integer',
        'jumlah_ml' => 'integer',
        'is_target_reached' => 'boolean',
        'tanggal' => 'date',
    ];

    /**
     * Relasi: setiap catatan air minum dimiliki oleh satu user
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}
