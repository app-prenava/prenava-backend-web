<?php

namespace App\Http\Controllers;

use App\Models\MaternalTriageScore;
use App\Models\User;
use App\Services\MaternalTriageService;
use App\Support\AuthToken;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class BidanTriageController extends Controller
{
    protected MaternalTriageService $triageService;

    public function __construct(MaternalTriageService $triageService)
    {
        $this->triageService = $triageService;
    }

    /**
     * Mengambil daftar antrean triase pasien Bidan terurut berdasarkan prioritas kegawatan
     * GET /api/bidan/patients/triage
     */
    public function getTriageQueue(Request $request): JsonResponse
    {
        // Validasi autentikasi JWT Bidan
        [$userId, $role] = AuthToken::uidRoleOrFail($request);

        if (! in_array($role, ['bidan', 'admin'])) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Akses ditolak. Fitur CDSS hanya dapat diakses oleh Tenaga Medis Bidan.',
            ], 403);
        }

        $queue = $this->triageService->getTriageQueue();

        $redCount    = $queue->where('triage_category', MaternalTriageScore::CATEGORY_RED)->count();
        $yellowCount = $queue->where('triage_category', MaternalTriageScore::CATEGORY_YELLOW)->count();
        $greenCount  = $queue->where('triage_category', MaternalTriageScore::CATEGORY_GREEN)->count();

        $formatted = $queue->map(function (MaternalTriageScore $score) {
            $user = $score->user;
            $pregnancy = $score->pregnancy;

            return [
                'triage_id'             => $score->id,
                'user_id'               => $score->user_id,
                'patient_name'          => $user ? $user->name : 'Pasien #' . $score->user_id,
                'patient_email'         => $user ? $user->email : null,
                'phone'                 => $user?->userProfile?->no_telepon,
                'gestational_age_weeks' => $pregnancy?->gestational_age_weeks,
                'pregnancy_status'      => $pregnancy?->status ?? 'ongoing',
                'triage_score'          => (float) $score->triage_score,
                'triage_category'       => $score->triage_category,
                'badge'                 => $score->getFormattedBadge(),
                'sub_scores'            => [
                    'stunting_weighted' => (float) $score->stunting_score,
                    'anemia_weighted'   => (float) $score->anemia_score,
                    'bp_weighted'       => (float) $score->bp_score,
                    'plate_weighted'    => (float) $score->plate_score,
                ],
                'completeness'          => [
                    'percentage'         => (float) ($score->completeness_percentage ?? 100.0),
                    'modalities_count'   => (int) ($score->modalities_count ?? 4),
                    'total_modalities'   => 4,
                    'missing_modalities' => $score->missing_modalities ?? [],
                    'badge'              => $score->getCompletenessBadge(),
                ],
                'primary_alert'         => $score->primary_alert,
                'suggested_action'      => $score->suggested_action,
                'calculated_at'         => $score->calculated_at?->toIso8601String(),
            ];
        });

        return response()->json([
            'status'         => 'success',
            'total_patients' => $formatted->count(),
            'summary'        => [
                'critical_red'    => $redCount,
                'warning_yellow'  => $yellowCount,
                'stable_green'    => $greenCount,
            ],
            'data'           => $formatted,
        ], 200);
    }

    /**
     * Mengambil riwayat dan rincian lengkap triase seorang pasien
     * GET /api/bidan/patients/{userId}/triage-detail
     */
    public function getPatientDetail(Request $request, int $userId): JsonResponse
    {
        [$authId, $role] = AuthToken::uidRoleOrFail($request);

        if (! in_array($role, ['bidan', 'admin'])) {
            return response()->json(['status' => 'error', 'message' => 'Akses ditolak.'], 403);
        }

        $user = User::with(['userProfile', 'pregnancies'])->find($userId);
        if (! $user) {
            return response()->json(['status' => 'error', 'message' => 'Pasien tidak ditemukan.'], 404);
        }

        $latestScore = MaternalTriageScore::where('user_id', $userId)
            ->latest('calculated_at')
            ->first();

        $history = MaternalTriageScore::where('user_id', $userId)
            ->latest('calculated_at')
            ->take(10)
            ->get();

        return response()->json([
            'status' => 'success',
            'patient' => [
                'user_id'      => $user->user_id,
                'name'         => $user->name,
                'email'        => $user->email,
                'profile'      => $user->userProfile,
                'pregnancies'  => $user->pregnancies,
            ],
            'latest_triage' => $latestScore ? [
                'triage_id'        => $latestScore->id,
                'triage_score'     => (float) $latestScore->triage_score,
                'triage_category'  => $latestScore->triage_category,
                'badge'            => $latestScore->getFormattedBadge(),
                'sub_scores'       => [
                    'stunting_weighted' => (float) $latestScore->stunting_score,
                    'anemia_weighted'   => (float) $latestScore->anemia_score,
                    'bp_weighted'       => (float) $latestScore->bp_score,
                    'plate_weighted'    => (float) $latestScore->plate_score,
                ],
                'completeness'     => [
                    'percentage'         => (float) ($latestScore->completeness_percentage ?? 100.0),
                    'modalities_count'   => (int) ($latestScore->modalities_count ?? 4),
                    'total_modalities'   => 4,
                    'missing_modalities' => $latestScore->missing_modalities ?? [],
                    'badge'              => $latestScore->getCompletenessBadge(),
                ],
                'primary_alert'    => $latestScore->primary_alert,
                'suggested_action' => $latestScore->suggested_action,
                'calculated_at'    => $latestScore->calculated_at?->toIso8601String(),
            ] : null,
            'history' => $history,
        ], 200);
    }

    /**
     * Memicu kalkulasi ulang skor triase pasien secara *on-demand*
     * POST /api/bidan/patients/{userId}/recalculate-triage
     */
    public function recalculate(Request $request, int $userId): JsonResponse
    {
        [$authId, $role] = AuthToken::uidRoleOrFail($request);

        if (! in_array($role, ['bidan', 'admin'])) {
            return response()->json(['status' => 'error', 'message' => 'Akses ditolak.'], 403);
        }

        try {
            $newScore = $this->triageService->calculatePatientTriage($userId);

            return response()->json([
                'status'  => 'success',
                'message' => 'Kalkulasi triase pasien berhasil diperbarui.',
                'data'    => [
                    'triage_id'        => $newScore->id,
                    'triage_score'     => (float) $newScore->triage_score,
                    'triage_category'  => $newScore->triage_category,
                    'badge'            => $newScore->getFormattedBadge(),
                    'sub_scores'       => [
                        'stunting_weighted' => (float) $newScore->stunting_score,
                        'anemia_weighted'   => (float) $newScore->anemia_score,
                        'bp_weighted'       => (float) $newScore->bp_score,
                        'plate_weighted'    => (float) $newScore->plate_score,
                    ],
                    'completeness'     => [
                        'percentage'         => (float) ($newScore->completeness_percentage ?? 100.0),
                        'modalities_count'   => (int) ($newScore->modalities_count ?? 4),
                        'total_modalities'   => 4,
                        'missing_modalities' => $newScore->missing_modalities ?? [],
                        'badge'              => $newScore->getCompletenessBadge(),
                    ],
                    'primary_alert'    => $newScore->primary_alert,
                    'suggested_action' => $newScore->suggested_action,
                    'calculated_at'    => $newScore->calculated_at?->toIso8601String(),
                ],
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Gagal menghitung triase: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mengambil status kelengkapan data & ringkasan pemantauan mandiri ibu hamil
     * GET /api/user/maternal-triage
     */
    public function getMyTriage(Request $request): JsonResponse
    {
        [$userId] = AuthToken::assertRoleFresh($request, 'ibu_hamil');

        $latestScore = MaternalTriageScore::where('user_id', $userId)
            ->latest('calculated_at')
            ->first();

        if (! $latestScore) {
            try {
                $latestScore = $this->triageService->calculatePatientTriage($userId);
            } catch (\Throwable $e) {
                Log::warning("Gagal auto-calculate initial triage for user {$userId}: " . $e->getMessage());
            }
        }

        $pct = (float) ($latestScore?->completeness_percentage ?? 0.0);
        $count = (int) ($latestScore?->modalities_count ?? 0);
        $missing = $latestScore?->missing_modalities ?? [
            'Skrining Stunting Maternal',
            'Pemeriksaan Anemia Mata',
            'Asesmen Tensi & Komplikasi',
            'Pemindaian Piring Nutrisi',
        ];

        return response()->json([
            'status' => 'success',
            'data'   => [
                'completeness_percentage' => $pct,
                'modalities_count'        => $count,
                'total_modalities'        => 4,
                'missing_modalities'      => $missing,
                'badge'                   => $latestScore?->getCompletenessBadge() ?? [
                    'percentage' => $pct,
                    'count'      => $count,
                    'label'      => $count > 0 ? "Skor Awal ({$count}/4)" : 'Belum Ada Data Skrining (0/4)',
                    'level'      => $count > 0 ? 'LOW' : 'EMPTY',
                    'badge_class'=> $count > 0 ? 'badge-initial' : 'badge-empty',
                    'color'      => $count > 0 ? '#FA8C16' : '#8C8C8C',
                    'bg'         => $count > 0 ? '#FFF7E6' : '#F5F5F5',
                ],
                'supportive_message'      => $count >= 4
                    ? 'Luar biasa! Seluruh 4 pemantauan mandiri bulan ini telah lengkap. Terus jaga kesehatan ya Bu!'
                    : 'Lengkapi pemantauan kesehatan mandiri di bawah ini agar Bidan dapat memantau kondisi Ibu secara optimal.',
                'calculated_at'           => $latestScore?->calculated_at?->toIso8601String(),
            ],
        ], 200);
    }
}
