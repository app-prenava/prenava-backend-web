<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaternalTriageScore extends Model
{
    use HasFactory;

    protected $table = 'maternal_triage_scores';

    public const CATEGORY_RED    = 'MERAH';
    public const CATEGORY_YELLOW = 'KUNING';
    public const CATEGORY_GREEN  = 'HIJAU';

    protected $fillable = [
        'user_id',
        'pregnancy_id',
        'triage_score',
        'triage_category',
        'stunting_score',
        'anemia_score',
        'bp_score',
        'plate_score',
        'completeness_percentage',
        'modalities_count',
        'missing_modalities',
        'is_dynamically_weighted',
        'primary_alert',
        'suggested_action',
        'calculated_at',
    ];

    protected $casts = [
        'triage_score'            => 'float',
        'stunting_score'          => 'float',
        'anemia_score'            => 'float',
        'bp_score'                => 'float',
        'plate_score'             => 'float',
        'completeness_percentage' => 'float',
        'modalities_count'        => 'integer',
        'missing_modalities'      => 'array',
        'is_dynamically_weighted' => 'boolean',
        'calculated_at'           => 'datetime',
    ];

    // ===== Relationships =====

    /**
     * Relasi ke pasien (Ibu Hamil)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    /**
     * Relasi ke rekam kehamilan aktif
     */
    public function pregnancy(): BelongsTo
    {
        return $this->belongsTo(Pregnancy::class, 'pregnancy_id', 'pregnancy_id');
    }

    // ===== Scopes =====

    /**
     * Filter hanya pasien prioritas darurat (MERAH)
     */
    public function scopeCritical($query)
    {
        return $query->where('triage_category', self::CATEGORY_RED);
    }

    /**
     * Filter berdasarkan kategori tertentu
     */
    public function scopeByCategory($query, string $category)
    {
        return $query->where('triage_category', $category);
    }

    /**
     * Ambil data triase terbaru untuk user tertentu
     */
    public function scopeLatestForUser($query, int $userId)
    {
        return $query->where('user_id', $userId)->latest('calculated_at');
    }

    /**
     * Urutan antrean prioritas Bidan (tertinggi ke terendah)
     */
    public function scopePriorityQueue($query)
    {
        return $query->orderBy('triage_score', 'desc');
    }

    // ===== Helper Methods =====

    public function isCritical(): bool
    {
        return $this->triage_category === self::CATEGORY_RED;
    }

    public function isWarning(): bool
    {
        return $this->triage_category === self::CATEGORY_YELLOW;
    }

    public function isSafe(): bool
    {
        return $this->triage_category === self::CATEGORY_GREEN;
    }

    /**
     * Mengembalikan struktur warna styling UI untuk Frontend React (Ant Design)
     */
    public function getFormattedBadge(): array
    {
        return match ($this->triage_category) {
            self::CATEGORY_RED => [
                'color'       => '#FF4D4F',
                'bg'          => '#FFF1F0',
                'border'      => '#FFA39E',
                'label'       => 'PRIORITAS 1 (MERAH - GAWAT)',
                'badge_class' => 'badge-red',
            ],
            self::CATEGORY_YELLOW => [
                'color'       => '#FAAD14',
                'bg'          => '#FFFBE6',
                'border'      => '#FFE58F',
                'label'       => 'PRIORITAS 2 (KUNING - WASPADA)',
                'badge_class' => 'badge-yellow',
            ],
            default => [
                'color'       => '#52C41A',
                'bg'          => '#F6FFED',
                'border'      => '#B7EB8F',
                'label'       => 'PRIORITAS 3 (HIJAU - AMAN)',
                'badge_class' => 'badge-green',
            ],
        };
    }

    /**
     * Mengembalikan status badge kelengkapan data (Completeness Index) untuk UI Bidan
     */
    public function getCompletenessBadge(): array
    {
        $pct = (float) ($this->completeness_percentage ?? 100.0);
        $count = (int) ($this->modalities_count ?? 4);

        if ($pct >= 100.0) {
            return [
                'percentage' => 100,
                'count'      => 4,
                'label'      => 'Lengkap (4/4 Parameter)',
                'level'      => 'HIGH',
                'badge_class'=> 'badge-complete',
                'color'      => '#52C41A',
                'bg'         => '#F6FFED',
            ];
        } elseif ($pct >= 75.0) {
            return [
                'percentage' => $pct,
                'count'      => $count,
                'label'      => 'Cukup Lengkap (3/4 Parameter)',
                'level'      => 'HIGH',
                'badge_class'=> 'badge-good',
                'color'      => '#1890FF',
                'bg'         => '#E6F7FF',
            ];
        } elseif ($pct >= 50.0) {
            return [
                'percentage' => $pct,
                'count'      => $count,
                'label'      => 'Parsial (2/4 Parameter)',
                'level'      => 'MODERATE',
                'badge_class'=> 'badge-partial',
                'color'      => '#FAAD14',
                'bg'         => '#FFFBE6',
            ];
        } elseif ($pct >= 25.0) {
            return [
                'percentage' => $pct,
                'count'      => $count,
                'label'      => 'Skor Awal (1/4 Parameter)',
                'level'      => 'LOW',
                'badge_class'=> 'badge-initial',
                'color'      => '#FA8C16',
                'bg'         => '#FFF7E6',
            ];
        } else {
            return [
                'percentage' => 0,
                'count'      => 0,
                'label'      => 'Belum Ada Data Skrining (0/4)',
                'level'      => 'EMPTY',
                'badge_class'=> 'badge-empty',
                'color'      => '#8C8C8C',
                'bg'         => '#F5F5F5',
            ];
        }
    }
}
