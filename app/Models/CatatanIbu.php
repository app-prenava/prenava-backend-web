<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CatatanIbu extends Model
{
    protected $table = 'catatanibu';
    protected $primaryKey = 'catatan_id';
    public $timestamps = true;

    protected $fillable = [
        'user_id',
        'tanggal_kunjungan',
        'status_kunjungan',
        'q1_demam',
        'q2_pusing',
        'q3_sulit_tidur',
        'q4_risiko_tb',
        'q5_gerakan_bayi',
        'q6_nyeri_perut',
        'q7_cairan_jalan_lahir',
        'q8_sakit_kencing',
        'q9_diare',
        'hasil_kunjungan',
        'systolic_bp',
        'diastolic_bp',
        'weight_kg',
        'fundal_height_cm',
        'fetal_heart_rate_bpm',
        'hemoglobin_gdl',
        'therapy_notes',
        'next_visit_date',
    ];

    protected $casts = [
        'tanggal_kunjungan' => 'date',
        'q1_demam' => 'boolean',
        'q2_pusing' => 'boolean',
        'q3_sulit_tidur' => 'boolean',
        'q4_risiko_tb' => 'boolean',
        'q5_gerakan_bayi' => 'boolean',
        'q6_nyeri_perut' => 'boolean',
        'q7_cairan_jalan_lahir' => 'boolean',
        'q8_sakit_kencing' => 'boolean',
        'q9_diare' => 'boolean',
        'systolic_bp' => 'integer',
        'diastolic_bp' => 'integer',
        'weight_kg' => 'float',
        'fundal_height_cm' => 'float',
        'fetal_heart_rate_bpm' => 'integer',
        'hemoglobin_gdl' => 'float',
        'next_visit_date' => 'date',
    ];

    /**
     * Relationship to User
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    /**
     * Get all questions as array
     */
    public function getQuestionsArray(): array
    {
        return [
            'q1_demam' => [
                'pertanyaan' => 'Demam lebih dari 2 hari',
                'jawaban' => $this->q1_demam,
            ],
            'q2_pusing' => [
                'pertanyaan' => 'Pusing/sakit kepala berat',
                'jawaban' => $this->q2_pusing,
            ],
            'q3_sulit_tidur' => [
                'pertanyaan' => 'Sulit tidur/cemas berlebih',
                'jawaban' => $this->q3_sulit_tidur,
            ],
            'q4_risiko_tb' => [
                'pertanyaan' => 'Risiko TB batuk lebih dari 2 minggu atau kontak serumah dengan penderita TB',
                'jawaban' => $this->q4_risiko_tb,
            ],
            'q5_gerakan_bayi' => [
                'pertanyaan' => 'Gerakan bayi Tidak ada atau Kurang dari 10x dalam 12 jam setelah minggu ke-24',
                'jawaban' => $this->q5_gerakan_bayi,
            ],
            'q6_nyeri_perut' => [
                'pertanyaan' => 'Nyeri perut hebat',
                'jawaban' => $this->q6_nyeri_perut,
            ],
            'q7_cairan_jalan_lahir' => [
                'pertanyaan' => 'Keluar cairan dari jalan lahir sangat banyak atau berbau',
                'jawaban' => $this->q7_cairan_jalan_lahir,
            ],
            'q8_sakit_kencing' => [
                'pertanyaan' => 'Sakit saat kencing Atau keluar keputihan atau gatal di daerah kemaluan',
                'jawaban' => $this->q8_sakit_kencing,
            ],
            'q9_diare' => [
                'pertanyaan' => 'Diare berulang',
                'jawaban' => $this->q9_diare,
            ],
        ];
    }
}
