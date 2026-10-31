<?php

namespace Tests\Unit;

use App\Models\MaternalTriageScore;
use App\Services\MaternalTriageService;
use PHPUnit\Framework\TestCase;

class MaternalTriageCalculationTest extends TestCase
{
    private MaternalTriageService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new MaternalTriageService();
    }

    /**
     * Skenario 1: Semua data modalitas lengkap (4/4) dan kondisi fisiologis normal.
     */
    public function test_full_modalities_normal_triage(): void
    {
        $stunting = ['has_data' => true, 'score' => 0.15, 'level' => 'LOW'];
        $anemia   = ['has_data' => true, 'score' => 0.10, 'level' => 'NORMAL'];
        $bp       = ['has_data' => true, 'score' => 0.20, 'systolic' => 110, 'diastolic' => 70];
        $plate    = ['has_data' => true, 'score' => 0.15, 'compliance' => 88];

        $res = $this->service->calculateScoreFromModalities($stunting, $anemia, $bp, $plate);

        $this->assertEquals(100.0, $res['completeness_percentage']);
        $this->assertEquals(4, $res['modalities_count']);
        $this->assertEmpty($res['missing_modalities']);
        $this->assertEquals(MaternalTriageScore::CATEGORY_GREEN, $res['triage_category']);
        $this->assertLessThan(0.40, $res['triage_score']);
        $this->assertFalse($res['is_override']);
    }

    /**
     * Skenario 2: Hanya 1 fitur terisi (Anemia Berat 0.85).
     * Normalisasi Dinamis harus mencegah skor terdistorsi oleh data dummy.
     */
    public function test_single_modality_dynamic_normalization(): void
    {
        $stunting = ['has_data' => false, 'score' => 0.25];
        $anemia   = ['has_data' => true,  'score' => 0.85, 'level' => 'SEVERE'];
        $bp       = ['has_data' => false, 'score' => 0.20];
        $plate    = ['has_data' => false, 'score' => 0.25];

        $res = $this->service->calculateScoreFromModalities($stunting, $anemia, $bp, $plate);

        $this->assertEquals(25.0, $res['completeness_percentage']);
        $this->assertEquals(1, $res['modalities_count']);
        $this->assertCount(3, $res['missing_modalities']);
        $this->assertEquals(0.85, $res['triage_score']);
        $this->assertEquals(MaternalTriageScore::CATEGORY_RED, $res['triage_category']);
        $this->assertStringContainsString('Indikasi Anemia Konjungtiva Mata', $res['primary_alert']);
        $this->assertStringContainsString('25%', $res['primary_alert']);
    }

    /**
     * Skenario 3: Hard Clinical Override karena Hipertensi Kritis / Preeklamsia (BP >= 0.85).
     */
    public function test_critical_blood_pressure_hard_override(): void
    {
        $stunting = ['has_data' => false, 'score' => 0.25];
        $anemia   = ['has_data' => false, 'score' => 0.10];
        $bp       = ['has_data' => true,  'score' => 0.88, 'systolic' => 155, 'diastolic' => 100];
        $plate    = ['has_data' => false, 'score' => 0.25];

        $res = $this->service->calculateScoreFromModalities($stunting, $anemia, $bp, $plate);

        $this->assertTrue($res['is_override']);
        $this->assertEquals(MaternalTriageScore::CATEGORY_RED, $res['triage_category']);
        $this->assertStringContainsString('Preeklamsia', $res['override_reason']);
    }

    /**
     * Skenario 4: Sinergi Risiko Mematikan (Stunting Ekstrem >= 0.80 dan Anemia Tinggi >= 0.70).
     */
    public function test_lethal_synergy_stunting_and_anemia_override(): void
    {
        $stunting = ['has_data' => true, 'score' => 0.85, 'level' => 'HIGH'];
        $anemia   = ['has_data' => true, 'score' => 0.72, 'level' => 'HIGH'];
        $bp       = ['has_data' => false, 'score' => 0.20];
        $plate    = ['has_data' => false, 'score' => 0.25];

        $res = $this->service->calculateScoreFromModalities($stunting, $anemia, $bp, $plate);

        $this->assertTrue($res['is_override']);
        $this->assertEquals(MaternalTriageScore::CATEGORY_RED, $res['triage_category']);
        $this->assertEquals(50.0, $res['completeness_percentage']);
        $this->assertStringContainsString('Sinergi Kritis', $res['override_reason']);
    }

    /**
     * Skenario 5: Pasien Baru (0 modalitas terisi). Graceful Handling ke HIJAU dasar (0.15).
     */
    public function test_brand_new_patient_zero_modalities(): void
    {
        $stunting = ['has_data' => false, 'score' => 0.25];
        $anemia   = ['has_data' => false, 'score' => 0.10];
        $bp       = ['has_data' => false, 'score' => 0.20];
        $plate    = ['has_data' => false, 'score' => 0.25];

        $res = $this->service->calculateScoreFromModalities($stunting, $anemia, $bp, $plate);

        $this->assertEquals(0.0, $res['completeness_percentage']);
        $this->assertEquals(0, $res['modalities_count']);
        $this->assertCount(4, $res['missing_modalities']);
        $this->assertEquals(0.15, $res['triage_score']);
        $this->assertEquals(MaternalTriageScore::CATEGORY_GREEN, $res['triage_category']);
        $this->assertStringContainsString('0%', $res['primary_alert']);
    }

    /**
     * Skenario 6: Uji Badge Kelengkapan Data Profil (Completeness Badge).
     */
    public function test_completeness_badge_levels(): void
    {
        $score = new MaternalTriageScore();

        // 100%
        $score->completeness_percentage = 100.0;
        $score->modalities_count = 4;
        $badge100 = $score->getCompletenessBadge();
        $this->assertEquals('HIGH', $badge100['level']);
        $this->assertEquals('badge-complete', $badge100['badge_class']);

        // 75%
        $score->completeness_percentage = 75.0;
        $score->modalities_count = 3;
        $badge75 = $score->getCompletenessBadge();
        $this->assertEquals('HIGH', $badge75['level']);
        $this->assertEquals('badge-good', $badge75['badge_class']);

        // 50%
        $score->completeness_percentage = 50.0;
        $score->modalities_count = 2;
        $badge50 = $score->getCompletenessBadge();
        $this->assertEquals('MODERATE', $badge50['level']);
        $this->assertEquals('badge-partial', $badge50['badge_class']);

        // 25%
        $score->completeness_percentage = 25.0;
        $score->modalities_count = 1;
        $badge25 = $score->getCompletenessBadge();
        $this->assertEquals('LOW', $badge25['level']);
        $this->assertEquals('badge-initial', $badge25['badge_class']);

        // 0%
        $score->completeness_percentage = 0.0;
        $score->modalities_count = 0;
        $badge0 = $score->getCompletenessBadge();
        $this->assertEquals('EMPTY', $badge0['level']);
        $this->assertEquals('badge-empty', $badge0['badge_class']);
    }
}
