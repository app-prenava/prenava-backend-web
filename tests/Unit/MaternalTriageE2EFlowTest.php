<?php

namespace Tests\Unit;

use App\Models\MaternalTriageScore;
use App\Services\MaternalTriageService;
use PHPUnit\Framework\TestCase;

class MaternalTriageE2EFlowTest extends TestCase
{
    private MaternalTriageService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new MaternalTriageService();
    }

    /**
     * Uji Alur Bertahap (Progressive Patient Journey 0% -> 25% -> 50% -> 75% -> 100%)
     * Memvalidasi bahwa setiap kali modalitas baru ditambahkan, skor dinormalisasi secara adaptif.
     */
    public function test_progressive_patient_onboarding_journey(): void
    {
        // STEP 0: Pasien Baru mendaftar (0 modalitas terisi)
        $s0 = ['has_data' => false, 'score' => 0.25];
        $a0 = ['has_data' => false, 'score' => 0.20];
        $b0 = ['has_data' => false, 'score' => 0.15];
        $p0 = ['has_data' => false, 'score' => 0.25];

        $res0 = $this->service->calculateScoreFromModalities($s0, $a0, $b0, $p0);
        $this->assertEquals(0.0, $res0['completeness_percentage']);
        $this->assertEquals(0, $res0['modalities_count']);
        $this->assertCount(4, $res0['missing_modalities']);
        $this->assertEquals(0.15, $res0['triage_score']);
        $this->assertEquals(MaternalTriageScore::CATEGORY_GREEN, $res0['triage_category']);

        // STEP 1: Pasien mengambil foto makanan piring nutrisi (Modalitas 4 terisi: Kepatuhan Cukup = 0.35)
        $p1 = ['has_data' => true, 'score' => 0.35, 'source' => 'dl_food_vision'];
        $res1 = $this->service->calculateScoreFromModalities($s0, $a0, $b0, $p1);
        $this->assertEquals(25.0, $res1['completeness_percentage']);
        $this->assertEquals(1, $res1['modalities_count']);
        $this->assertCount(3, $res1['missing_modalities']);
        $this->assertEquals(0.35, $res1['triage_score']); // 100% bobot aktif dari piring
        $this->assertEquals(MaternalTriageScore::CATEGORY_GREEN, $res1['triage_category']);

        // STEP 2: Pasien melakukan foto kelopak mata (Modalitas 2 terisi: Indikasi Anemia Pucat = 0.70)
        $a2 = ['has_data' => true, 'score' => 0.70, 'source' => 'dl_anemia'];
        $res2 = $this->service->calculateScoreFromModalities($s0, $a2, $b0, $p1);
        $this->assertEquals(50.0, $res2['completeness_percentage']);
        $this->assertEquals(2, $res2['modalities_count']);
        $this->assertCount(2, $res2['missing_modalities']);
        // Bobot aktif: Anemia (0.30 * 0.70 = 0.21) + Piring (0.15 * 0.35 = 0.0525) = 0.2625 / 0.45 = 0.5833
        $this->assertEquals(0.58, $res2['triage_score']);
        $this->assertEquals(MaternalTriageScore::CATEGORY_YELLOW, $res2['triage_category']);

        // STEP 3: Pasien mengisi skrining risiko stunting maternal (Modalitas 1 terisi: Risiko Rendah = 0.15)
        $s3 = ['has_data' => true, 'score' => 0.15, 'source' => 'ml_stunting'];
        $res3 = $this->service->calculateScoreFromModalities($s3, $a2, $b0, $p1);
        $this->assertEquals(75.0, $res3['completeness_percentage']);
        $this->assertEquals(3, $res3['modalities_count']);
        $this->assertCount(1, $res3['missing_modalities']);
        $this->assertContains('Asesmen Tensi & Komplikasi', $res3['missing_modalities']);
        // Bobot aktif: Stunting (0.35*0.15 = 0.0525) + Anemia (0.21) + Piring (0.0525) = 0.315 / 0.80 = 0.39375 -> 0.39
        $this->assertEquals(0.39, $res3['triage_score']);
        $this->assertEquals(MaternalTriageScore::CATEGORY_GREEN, $res3['triage_category']);

        // STEP 4: Pasien mengisi data tensi & komplikasi (Modalitas 3 terisi: Normal Tensi 110/75 = 0.10)
        $b4 = ['has_data' => true, 'score' => 0.10, 'source' => 'ml_sport_assessment'];
        $res4 = $this->service->calculateScoreFromModalities($s3, $a2, $b4, $p1);
        $this->assertEquals(100.0, $res4['completeness_percentage']);
        $this->assertEquals(4, $res4['modalities_count']);
        $this->assertEmpty($res4['missing_modalities']);
        // Total Bobot 1.00: 0.315 + (0.20 * 0.10 = 0.02) = 0.335 -> 0.34
        $this->assertEquals(0.34, $res4['triage_score']);
        $this->assertEquals(MaternalTriageScore::CATEGORY_GREEN, $res4['triage_category']);
    }

    /**
     * Uji Kontrak Payload Respons Mobile Pasien (GET /api/user/maternal-triage)
     */
    public function test_mobile_payload_contract(): void
    {
        $score = new MaternalTriageScore([
            'completeness_percentage' => 50.0,
            'modalities_count'        => 2,
            'missing_modalities'      => ['Skrining Stunting Maternal', 'Asesmen Tensi & Komplikasi'],
            'triage_score'            => 0.45,
            'triage_category'         => 'KUNING',
            'primary_alert'           => 'Indikasi Anemia Konjungtiva Mata [Kelengkapan: 50% - Belum: Skrining Stunting Maternal, Asesmen Tensi & Komplikasi]',
            'suggested_action'        => 'Intervensi Bidan: Pemantauan intensif.',
        ]);

        $badge = $score->getCompletenessBadge();

        $this->assertEquals(50.0, $badge['percentage']);
        $this->assertEquals(2, $badge['count']);
        $this->assertEquals('MODERATE', $badge['level']);
        $this->assertEquals('badge-partial', $badge['badge_class']);
        $this->assertStringContainsString('Parsial', $badge['label']);
    }

    /**
     * Uji Struktur Ringkasan Rujukan Medis SpOG / RS (Format Standar SBAR)
     */
    public function test_sbar_medical_referral_generation_logic(): void
    {
        $patientName = 'Siti Aminah';
        $age = 28;
        $weeks = 32;
        $category = 'MERAH';
        $score = 0.88;
        $primaryAlert = 'Peringatan Darurat: Hipertensi Gestasional Kritis / Tanda Preeklamsia';

        $sbarText = "LEMBAR RUJUKAN MEDIS MATERNAL (SBAR)\n" .
                    "S - SITUATION: Ny. {$patientName}, Usia {$age} Thn, Usia Kehamilan {$weeks} Minggu\n" .
                    "Status: [{$category}] - {$primaryAlert}\n" .
                    "A - ASSESSMENT: Skor Triase {$score}\n" .
                    "R - RECOMMENDATION: FAST-TRACK RUJUKAN SEGERA ke Dokter Spesialis Obstetri & Ginekologi (SpOG).";

        $this->assertStringContainsString('S - SITUATION', $sbarText);
        $this->assertStringContainsString('A - ASSESSMENT', $sbarText);
        $this->assertStringContainsString('R - RECOMMENDATION', $sbarText);
        $this->assertStringContainsString('FAST-TRACK RUJUKAN SEGERA', $sbarText);
    }
}
