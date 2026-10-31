<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pregnancy extends Model
{
    use HasFactory;

    protected $table = 'pregnancies';
    protected $primaryKey = 'pregnancy_id';

    protected $fillable = [
        'user_id',
        'lmp_date',
        'gestational_age_weeks',
        'status',
        'multiple_gestation',
    ];

    protected $casts = [
        'lmp_date'              => 'date',
        'gestational_age_weeks' => 'integer',
        'multiple_gestation'    => 'boolean',
    ];

    /**
     * Relasi ke User (Ibu Hamil)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    /**
     * Relasi ke riwayat skor triase maternal
     */
    public function triageScores(): HasMany
    {
        return $this->hasMany(MaternalTriageScore::class, 'pregnancy_id', 'pregnancy_id');
    }

    /**
     * Skor triase terbaru
     */
    public function latestTriageScore()
    {
        return $this->hasOne(MaternalTriageScore::class, 'pregnancy_id', 'pregnancy_id')->latestOfMany('calculated_at');
    }
}
