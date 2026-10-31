<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaternalVoiceConsultation extends Model
{
    use HasFactory;

    protected $table = 'maternal_voice_consultations';
    protected $primaryKey = 'consultation_id';

    protected $fillable = [
        'user_id',
        'pregnancy_id',
        'audio_file_path',
        'transcribed_text',
        'ai_answer',
        'reference_source',
        'provider_used',
        'is_emergency',
        'danger_level',
        'danger_keywords',
        'recommended_action',
    ];

    protected $casts = [
        'is_emergency'    => 'boolean',
        'danger_keywords' => 'array',
        'created_at'      => 'datetime',
        'updated_at'      => 'datetime',
    ];

    /**
     * Relasi ke User (Ibu Hamil)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    /**
     * Relasi ke Kehamilan
     */
    public function pregnancy(): BelongsTo
    {
        return $this->belongsTo(Pregnancy::class, 'pregnancy_id', 'pregnancy_id');
    }
}
