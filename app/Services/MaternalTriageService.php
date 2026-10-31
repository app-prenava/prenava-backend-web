<?php

namespace App\Services;

use App\Models\User;
use App\Models\Pregnancy;
use App\Models\StuntingPrediction;
use App\Models\HealthScanHistory;
use App\Models\PregnancyAssessment;
use App\Models\MaternalTriageScore;
use App\Models\FoodScan;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class MaternalTriageService
{
    // Bobot Komposit Standar Kemenkes RI / POGI
    public const WEIGHT_STUNTING = 0.35;
    public const WEIGHT_ANEMIA   = 0.30;
    public const WEIGHT_BP       = 0.20;
    public const WEIGHT_PLATE    = 0.15;

    // Ambang Batas Klasifikasi Kegawatan
    public const THRESHOLD_RED    = 0.70;
    public const THRESHOLD_YELLOW = 0.40;

    /**
     * Menghitung skor triase klinis komposit untuk pasien tunggal dengan
     * Normalisasi Bobot Dinamis (Dynamic Weight Normalization) dan Indeks Kelengkapan Data.
     */
    public function calculatePatientTriage(int $userId, ?int $pregnancyId = null): MaternalTriageScore
    {
        $user = User::with(['userProfile'])->find($userId);
        if (! $user) {
            throw new \InvalidArgumentException("User dengan ID {$userId} tidak ditemukan.");
        }

        // Cari kehamilan aktif jika pregnancyId tidak disertakan
        if (! $pregnancyId) {
            $pregnancy = Pregnancy::where('user_id', $userId)
                ->where('status', 'ongoing')
                ->latest()
                ->first()
                ?? Pregnancy::where('user_id', $userId)->latest()->first();

            $pregnancyId = $pregnancy?->pregnancy_id;
        } else {
            $pregnancy = Pregnancy::find($pregnancyId);
        }

        // 1. Evaluasi Data Aktual dari 4 Modalitas Layanan AI / Form Klinis
        $stunting = $this->evaluateStuntingRiskData($userId);
        $anemia   = $this->evaluateAnemiaRiskData($userId);
        $bp       = $this->evaluateBloodPressureData($pregnancyId);
        $plate    = $this->evaluatePlateComplianceData($userId);

        // 2. Kalkulasi Skor Triase Dinamis & Kelengkapan Data
        $calc = $this->calculateScoreFromModalities($stunting, $anemia, $bp, $plate);

        // 3. Simpan rekam skor triase ke database
        $triageRecord = MaternalTriageScore::create([
            'user_id'                 => $userId,
            'pregnancy_id'            => $pregnancyId,
            'triage_score'            => $calc['triage_score'],
            'triage_category'         => $calc['triage_category'],
            'stunting_score'          => $calc['stunting_score'],
            'anemia_score'            => $calc['anemia_score'],
            'bp_score'                => $calc['bp_score'],
            'plate_score'             => $calc['plate_score'],
            'completeness_percentage' => $calc['completeness_percentage'],
            'modalities_count'        => $calc['modalities_count'],
            'missing_modalities'      => $calc['missing_modalities'],
            'is_dynamically_weighted' => $calc['is_dynamically_weighted'],
            'primary_alert'           => $calc['primary_alert'],
            'suggested_action'        => $calc['suggested_action'],
            'calculated_at'           => now(),
        ]);

        Log::info("CDSS Dynamic Triage calculated for User {$userId}: Score {$calc['triage_score']} [{$calc['triage_category']}] - Kelengkapan: {$calc['completeness_percentage']}%");

        return $triageRecord;
    }

    /**
     * Menghitung kalkulasi triase dari 4 modalitas parameter (Pure Calculation Logic)
     */
    public function calculateScoreFromModalities(array $stunting, array $anemia, array $bp, array $plate): array
    {
        // 1. Evaluasi Indeks Kelengkapan Data (Data Completeness Index)
        $filledCount = 0;
        $missingModalities = [];

        if ($stunting['has_data']) {
            $filledCount++;
        } else {
            $missingModalities[] = 'Skrining Stunting Maternal';
        }

        if ($anemia['has_data']) {
            $filledCount++;
        } else {
            $missingModalities[] = 'Pemeriksaan Anemia Mata';
        }

        if ($bp['has_data']) {
            $filledCount++;
        } else {
            $missingModalities[] = 'Asesmen Tensi & Komplikasi';
        }

        if ($plate['has_data']) {
            $filledCount++;
        } else {
            $missingModalities[] = 'Pemindaian Piring Nutrisi';
        }

        $completenessPct = round(($filledCount / 4.0) * 100, 1);

        // 2. Normalisasi Bobot Dinamis (Dynamic Weight Normalization)
        $sumAvailableWeights = 0.0;
        $weightedSum = 0.0;

        if ($stunting['has_data']) {
            $sumAvailableWeights += self::WEIGHT_STUNTING;
            $weightedSum += ($stunting['score'] * self::WEIGHT_STUNTING);
        }
        if ($anemia['has_data']) {
            $sumAvailableWeights += self::WEIGHT_ANEMIA;
            $weightedSum += ($anemia['score'] * self::WEIGHT_ANEMIA);
        }
        if ($bp['has_data']) {
            $sumAvailableWeights += self::WEIGHT_BP;
            $weightedSum += ($bp['score'] * self::WEIGHT_BP);
        }
        if ($plate['has_data']) {
            $sumAvailableWeights += self::WEIGHT_PLATE;
            $weightedSum += ($plate['score'] * self::WEIGHT_PLATE);
        }

        if ($filledCount === 0) {
            // Pasien baru belum memiliki rekam skrining apa pun (Profil Dasar Aman)
            $rawScore = 0.15;
            $triageScore = 0.15;
        } else {
            // Normalisasi proporsional terhadap total bobot fitur yang benar-benar terisi
            $rawScore = $sumAvailableWeights > 0 ? ($weightedSum / $sumAvailableWeights) : 0.15;
            $triageScore = round(min(1.00, max(0.00, $rawScore)), 2);
        }

        // Komponen Sub-skor Terbobot (untuk visualisasi rincian kontribusi di portal Bidan)
        $stuntingWeighted = round($stunting['has_data'] ? ($stunting['score'] * self::WEIGHT_STUNTING) : 0.00, 2);
        $anemiaWeighted   = round($anemia['has_data']   ? ($anemia['score'] * self::WEIGHT_ANEMIA) : 0.00, 2);
        $bpWeighted       = round($bp['has_data']       ? ($bp['score'] * self::WEIGHT_BP) : 0.00, 2);
        $plateWeighted    = round($plate['has_data']    ? ($plate['score'] * self::WEIGHT_PLATE) : 0.00, 2);

        // 3. Evaluasi Pemutus Darurat Medis (Hard Clinical Override - Patient Safety First)
        $isOverrideRed = false;
        $overrideReason = null;

        // Pemicu Override 1: Tensi sangat kritis / Hipertensi Gestasional berat
        if ($bp['has_data'] && $bp['score'] >= 0.85) {
            $isOverrideRed = true;
            $overrideReason = "Peringatan Darurat: Hipertensi Gestasional Kritis / Tanda Preeklamsia";
        }

        // Pemicu Override 2: Sinergi Mematikan (Risiko Stunting Ekstrem >= 80% DAN Anemia Tinggi >= 70%)
        if ($stunting['has_data'] && $anemia['has_data']) {
            if ($stunting['score'] >= 0.80 && $anemia['score'] >= 0.70) {
                $isOverrideRed = true;
                $overrideReason = "Sinergi Kritis: Risiko Stunting Ekstrem disertai Anemia Berat";
            }
        }

        // Penentuan Kategori Kode Warna
        if ($isOverrideRed || $triageScore >= self::THRESHOLD_RED) {
            $category = MaternalTriageScore::CATEGORY_RED;
        } elseif ($triageScore >= self::THRESHOLD_YELLOW) {
            $category = MaternalTriageScore::CATEGORY_YELLOW;
        } else {
            $category = MaternalTriageScore::CATEGORY_GREEN;
        }

        // 4. Rumuskan Peringatan Utama (Primary Alert) & Saran Intervensi Klinis Terstruktur
        $primaryAlert = $this->generatePrimaryAlert(
            $stunting['has_data'] ? $stunting['score'] : 0.0,
            $anemia['has_data'] ? $anemia['score'] : 0.0,
            $bp['has_data'] ? $bp['score'] : 0.0,
            $category,
            $plate['has_data'] ? $plate['score'] : 0.0,
            $completenessPct,
            $missingModalities,
            $overrideReason
        );

        $suggestedAction = $this->generateSuggestedAction($category, $primaryAlert, $completenessPct, $missingModalities);

        return [
            'triage_score'            => $triageScore,
            'triage_category'         => $category,
            'stunting_score'          => $stuntingWeighted,
            'anemia_score'            => $anemiaWeighted,
            'bp_score'                => $bpWeighted,
            'plate_score'             => $plateWeighted,
            'completeness_percentage' => $completenessPct,
            'modalities_count'        => $filledCount,
            'missing_modalities'      => $missingModalities,
            'is_dynamically_weighted' => true,
            'is_override'             => $isOverrideRed,
            'override_reason'         => $overrideReason,
            'primary_alert'           => $primaryAlert,
            'suggested_action'        => $suggestedAction,
        ];
    }

    /**
     * Mengambil seluruh antrean triase pasien Bidan terurut descending
     * (Pasien MERAH selalu berada di urutan teratas)
     */
    public function getTriageQueue(): Collection
    {
        // Ambil ID seluruh pasien ibu hamil yang memiliki riwayat triase
        $userIds = MaternalTriageScore::select('user_id')
            ->distinct()
            ->pluck('user_id');

        $queue = collect();

        foreach ($userIds as $uid) {
            $latestScore = MaternalTriageScore::where('user_id', $uid)
                ->with(['user', 'pregnancy'])
                ->latest('calculated_at')
                ->first();

            if ($latestScore && $latestScore->user) {
                $queue->push($latestScore);
            }
        }

        // Urutkan berdasarkan skor triase tertinggi ke terendah
        return $queue->sortByDesc('triage_score')->values();
    }

    /**
     * Hitung ulang seluruh pasien aktif yang terdaftar di sistem
     */
    public function recalculateAllActivePatients(): int
    {
        $activePatients = User::where('role', 'ibu_hamil')
            ->where('is_active', true)
            ->get();

        $count = 0;
        foreach ($activePatients as $patient) {
            try {
                $this->calculatePatientTriage($patient->user_id);
                $count++;
            } catch (\Throwable $e) {
                Log::error("Failed recalculating triage for user {$patient->user_id}: " . $e->getMessage());
            }
        }

        return $count;
    }

    // ==========================================
    // EVALUATION METHODS DENGAN DATA STATUS
    // ==========================================

    public function evaluateStuntingRiskData(int $userId): array
    {
        $stunting = StuntingPrediction::where('user_id', $userId)
            ->where('status', StuntingPrediction::STATUS_SUCCESS)
            ->latest()
            ->first();

        if ($stunting && $stunting->probability !== null) {
            return [
                'has_data' => true,
                'score'    => (float) $stunting->probability,
                'source'   => 'ml_stunting',
                'record'   => $stunting,
            ];
        }

        return [
            'has_data' => false,
            'score'    => 0.25, // Nilai netral aman
            'source'   => null,
            'record'   => null,
        ];
    }

    public function evaluateAnemiaRiskData(int $userId): array
    {
        $scan = HealthScanHistory::where('user_id', $userId)
            ->where('type', 'anemia')
            ->latest()
            ->first();

        if ($scan && ! empty($scan->result)) {
            $res = $scan->result;
            $label = strtolower($res['label'] ?? $res['prediction'] ?? '');
            $conf  = (float) ($res['confidence'] ?? 0.50);

            $score = str_contains($label, 'anemia') && ! str_contains($label, 'non')
                ? min(1.0, max(0.60, $conf))
                : min(0.20, max(0.05, 1.0 - $conf));

            return [
                'has_data' => true,
                'score'    => $score,
                'source'   => 'dl_anemia',
                'record'   => $scan,
            ];
        }

        return [
            'has_data' => false,
            'score'    => 0.20,
            'source'   => null,
            'record'   => null,
        ];
    }

    public function evaluateBloodPressureData(?int $pregnancyId): array
    {
        if (! $pregnancyId) {
            return [
                'has_data' => false,
                'score'    => 0.15,
                'source'   => null,
                'record'   => null,
            ];
        }

        $assessment = PregnancyAssessment::where('pregnancy_id', $pregnancyId)
            ->latest()
            ->first();

        if (! $assessment) {
            return [
                'has_data' => false,
                'score'    => 0.15,
                'source'   => null,
                'record'   => null,
            ];
        }

        $risk = 0.10;

        // Faktor tensi
        if ($assessment->hypertension) {
            $risk += 0.40;
        }

        // Faktor plasenta previa
        if ($assessment->placenta_previa) {
            $risk += 0.30;
        }

        // Riwayat komplikasi sebelumnya
        if ($assessment->previous_complications) {
            $risk += 0.20;
        }

        // Demam tinggi saat asesmen
        if ($assessment->is_fever) {
            $risk += 0.15;
        }

        // Diabetes gestasional
        if ($assessment->gestational_diabetes || $assessment->is_diabetes) {
            $risk += 0.15;
        }

        return [
            'has_data' => true,
            'score'    => min(1.00, max(0.10, $risk)),
            'source'   => 'ml_sport_assessment',
            'record'   => $assessment,
        ];
    }

    public function evaluatePlateComplianceData(int $userId): array
    {
        $latestScan = FoodScan::where('user_id', $userId)
            ->latest('created_at')
            ->first();

        if (! $latestScan) {
            return [
                'has_data' => false,
                'score'    => 0.25,
                'source'   => null,
                'record'   => null,
            ];
        }

        // Jika piring tidak memiliki protein hewani (Defisit Heme-Iron / Circuit Breaker)
        if (! $latestScan->has_iron_protein) {
            $score = 0.75;
        } else {
            $score = match ($latestScan->balance_status) {
                'SEIMBANG'        => 0.10,
                'CUKUP_SEIMBANG'  => 0.35,
                'KURANG_SEIMBANG' => 0.75,
                default           => 0.25,
            };
        }

        return [
            'has_data' => true,
            'score'    => $score,
            'source'   => 'dl_food_vision',
            'record'   => $latestScan,
        ];
    }

    // ==========================================
    // BACKWARD COMPATIBLE EVALUATION HELPERS
    // ==========================================

    public function evaluateStuntingRisk(int $userId): float
    {
        return $this->evaluateStuntingRiskData($userId)['score'];
    }

    public function evaluateAnemiaRisk(int $userId): float
    {
        return $this->evaluateAnemiaRiskData($userId)['score'];
    }

    public function evaluateBloodPressureAndComplications(?int $pregnancyId): float
    {
        return $this->evaluateBloodPressureData($pregnancyId)['score'];
    }

    public function evaluatePlateCompliance(int $userId): float
    {
        return $this->evaluatePlateComplianceData($userId)['score'];
    }

    // ==========================================
    // ALERT & SUGGESTED ACTION GENERATION
    // ==========================================

    private function generatePrimaryAlert(
        float $stunting,
        float $anemia,
        float $bp,
        string $category,
        float $plate = 0.25,
        float $completenessPct = 100.0,
        array $missingModalities = [],
        ?string $overrideReason = null
    ): string {
        $alerts = [];

        if ($overrideReason) {
            $alerts[] = $overrideReason;
        }

        if ($stunting >= 0.70) {
            $pct = round($stunting * 100);
            $alerts[] = "Risiko Stunting Sangat Tinggi ({$pct}%)";
        } elseif ($stunting >= 0.50) {
            $pct = round($stunting * 100);
            $alerts[] = "Waspada Risiko Stunting Sedang ({$pct}%)";
        }

        if ($anemia >= 0.60) {
            $alerts[] = "Indikasi Anemia Konjungtiva Mata";
        }

        if ($bp >= 0.50) {
            $alerts[] = "Tanda Hipertensi / Komplikasi Obstetri";
        }

        if ($plate >= 0.70) {
            $alerts[] = "Defisit Kepatuhan Gizi Piring / Kurang Zat Besi";
        }

        if (empty($alerts)) {
            $baseText = $category === MaternalTriageScore::CATEGORY_GREEN
                ? "Seluruh parameter maternal terpantau dalam batas aman."
                : "Parameter kesehatan memerlukan pemantauan berkala.";
        } else {
            $baseText = implode(' & ', array_unique($alerts));
        }

        // Jika data belum lengkap 100%, tambahkan catatan kelengkapan agar Bidan terinformasi
        if ($completenessPct < 100.0 && ! empty($missingModalities)) {
            $baseText .= " [Kelengkapan: {$completenessPct}% - Belum: " . implode(', ', $missingModalities) . "]";
        }

        return $baseText;
    }

    private function generateSuggestedAction(
        string $category,
        string $primaryAlert,
        float $completenessPct = 100.0,
        array $missingModalities = []
    ): string {
        $baseAction = match ($category) {
            MaternalTriageScore::CATEGORY_RED => "PRIORITAS TINGGI: Pasien terindikasi kegawatan maternal ({$primaryAlert}). Rujuk segera ke dokter spesialis Sp.OG / RS PONED terdekat. Jadwalkan USG Trimester 3, lakukan tes laboratorium darah lengkap (Hb & Feritin), dan berikan suplemen zat besi dosis tinggi.",
            MaternalTriageScore::CATEGORY_YELLOW => "PANTAUAN INTENSIF: Pasien terindikasi defisiensi ({$primaryAlert}). Berikan konseling gizi 'Isi Piringku' Kemenkes, pastikan kepatuhan minum 90 Tablet Tambah Darah (TTD), dan jadwalkan kontrol ulang dalam 7 hari.",
            default => "KONDISI STABIL: Lanjutkan pemantauan rutin kehamilan (ANC) sesuai jadwal Buku KIA, pertahankan asupan nutrisi mikro seimbang, dan anjurkan aktivitas fisik ringan maternal.",
        };

        if ($completenessPct < 100.0 && ! empty($missingModalities)) {
            $baseAction .= " (Tindakan Bidan Tambahan: Profil skrining terisi {$completenessPct}%. Anjurkan ibu melengkapi " . implode(', ', $missingModalities) . " pada kunjungan ini).";
        }

        return $baseAction;
    }
}
