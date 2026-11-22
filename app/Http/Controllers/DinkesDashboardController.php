<?php

namespace App\Http\Controllers;

use App\Models\District;
use App\Models\Province;
use App\Models\Regency;
use App\Models\UserDinkes;
use App\Models\BidanApplication;
use App\Models\BidanSubscription;
use App\Models\HealthScanHistory;
use App\Models\StuntingPrediction;
use App\Support\AuthToken;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DinkesDashboardController extends Controller
{
    /**
     * Aggregated dashboard scoped strictly to the authenticated Dinkes Kab/Kota
     * GET /api/dinkes/dashboard
     */
    public function index(Request $request): JsonResponse
    {
        [$userId, $role] = AuthToken::uidRoleOrFail($request);

        if ($role !== 'dinkes') {
            return response()->json([
                'status' => 'error',
                'message' => 'Akses ditolak: hanya akun Dinkes yang dapat mengakses dashboard ini.',
            ], 403);
        }

        $dinkesProfile = UserDinkes::where('user_id', $userId)->first();

        if (!$dinkesProfile || empty($dinkesProfile->regency_code)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Akun Dinkes belum terhubung ke Kabupaten/Kota. Hubungi Admin Prenava untuk menetapkan wilayah.',
            ], 422);
        }

        $regency = Regency::with('province')->find($dinkesProfile->regency_code);
        if (!$regency) {
            return response()->json([
                'status' => 'error',
                'message' => 'Kode wilayah Kabupaten/Kota tidak ditemukan di master wilayah.',
            ], 422);
        }

        $regencyCode = $regency->code;

        // 1. Master list of all districts in this regency
        $districts = District::where('regency_code', $regencyCode)
            ->orderBy('name')
            ->get(['code', 'name']);

        $districtStats = [];
        foreach ($districts as $district) {
            $districtStats[$district->code] = [
                'district_code' => $district->code,
                'district_name' => $district->name,
                'ibu_hamil_count' => 0,
                'pmb_count' => 0,
                'trimester_1' => 0,
                'trimester_2' => 0,
                'trimester_3' => 0,
                'pasca_melahirkan' => 0,
                'risk_anemia' => 0,
                'risk_depression' => 0,
                'risk_stunting' => 0,
                'high_risk_ibu_count' => 0,
            ];
        }

        // 2. All active Ibu Hamil in this regency
        $ibuRows = DB::table('users')
            ->join('user_profile', 'user_profile.user_id', '=', 'users.user_id')
            ->where('users.role', 'ibu_hamil')
            ->where('users.is_active', true)
            ->where('user_profile.regency_code', $regencyCode)
            ->select([
                'users.user_id',
                'user_profile.district_code',
                'user_profile.status_kandungan',
            ])
            ->get();

        $ibuUserIds = $ibuRows->pluck('user_id')->all();

        // 3. Pregnancy age sources (latest ongoing pregnancy / pregnancy calculator)
        $pregnanciesByUser = [];
        $calculatorsByUser = [];
        $latestAnemiaByUser = [];
        $latestDepressionByUser = [];
        $latestStuntingByUser = [];

        if (!empty($ibuUserIds)) {
            $pregnancies = DB::table('pregnancies')
                ->whereIn('user_id', $ibuUserIds)
                ->where('status', 'ongoing')
                ->orderBy('created_at', 'desc')
                ->orderBy('pregnancy_id', 'desc')
                ->get(['user_id', 'lmp_date', 'gestational_age_weeks']);

            foreach ($pregnancies as $p) {
                if (!isset($pregnanciesByUser[$p->user_id])) {
                    $pregnanciesByUser[$p->user_id] = $p;
                }
            }

            $calculators = DB::table('pregnancy_calculators')
                ->whereIn('user_id', $ibuUserIds)
                ->orderBy('created_at', 'desc')
                ->orderBy('id', 'desc')
                ->get(['user_id', 'hpht']);

            foreach ($calculators as $c) {
                if (!isset($calculatorsByUser[$c->user_id])) {
                    $calculatorsByUser[$c->user_id] = $c;
                }
            }

            // Latest anemia & depression scan per user
            $scans = HealthScanHistory::whereIn('user_id', $ibuUserIds)
                ->orderBy('created_at', 'desc')
                ->orderBy('id', 'desc')
                ->get(['user_id', 'type', 'result']);

            foreach ($scans as $scan) {
                if ($scan->type === 'anemia' && !isset($latestAnemiaByUser[$scan->user_id])) {
                    $latestAnemiaByUser[$scan->user_id] = $scan->result;
                } elseif ($scan->type === 'depression' && !isset($latestDepressionByUser[$scan->user_id])) {
                    $latestDepressionByUser[$scan->user_id] = $scan->result;
                }
            }

            // Latest successful stunting prediction per user
            $stuntings = StuntingPrediction::whereIn('user_id', $ibuUserIds)
                ->where('status', StuntingPrediction::STATUS_SUCCESS)
                ->orderBy('created_at', 'desc')
                ->orderBy('id', 'desc')
                ->get(['user_id', 'prediction', 'risk_label']);

            foreach ($stuntings as $st) {
                if (!isset($latestStuntingByUser[$st->user_id])) {
                    $latestStuntingByUser[$st->user_id] = $st;
                }
            }
        }

        // 4. Aggregate Ibu Hamil by trimester, risk, and district
        $trimesterDist = [
            'trimester_1' => 0,
            'trimester_2' => 0,
            'trimester_3' => 0,
            'pasca_melahirkan' => 0,
            'belum_diisi' => 0,
        ];

        $riskDist = [
            'anemia' => ['anemia' => 0, 'non_anemia' => 0, 'belum_skrining' => 0],
            'depression' => ['detected' => 0, 'safe' => 0, 'belum_skrining' => 0],
            'stunting' => ['tinggi' => 0, 'rendah' => 0, 'belum_skrining' => 0],
        ];

        $highRiskIbuTotal = 0;

        foreach ($ibuRows as $ibu) {
            $uid = $ibu->user_id;
            $dCode = $ibu->district_code;

            // Resolve trimester
            $trimesterKey = $this->resolveTrimester(
                $pregnanciesByUser[$uid] ?? null,
                $calculatorsByUser[$uid] ?? null,
                $ibu->status_kandungan
            );
            $trimesterDist[$trimesterKey]++;

            // Evaluate risks (latest per user)
            $hasAnemia = false;
            if (isset($latestAnemiaByUser[$uid])) {
                $anemiaPayload = is_string($latestAnemiaByUser[$uid])
                    ? json_decode($latestAnemiaByUser[$uid], true)
                    : $latestAnemiaByUser[$uid];
                $label = strtolower(trim((string) ($anemiaPayload['label'] ?? '')));
                if ($label === 'anemia') {
                    $riskDist['anemia']['anemia']++;
                    $hasAnemia = true;
                } elseif ($label === 'non-anemia' || $label === 'non anemia') {
                    $riskDist['anemia']['non_anemia']++;
                } else {
                    $riskDist['anemia']['belum_skrining']++;
                }
            } else {
                $riskDist['anemia']['belum_skrining']++;
            }

            $hasDepression = false;
            if (isset($latestDepressionByUser[$uid])) {
                $depPayload = is_string($latestDepressionByUser[$uid])
                    ? json_decode($latestDepressionByUser[$uid], true)
                    : $latestDepressionByUser[$uid];
                $level = strtolower((string) ($depPayload['level'] ?? $depPayload['depression_level'] ?? ''));
                if (str_contains($level, 'indikator depresi') && !str_contains($level, 'tidak signifikan')) {
                    $riskDist['depression']['detected']++;
                    $hasDepression = true;
                } else {
                    $riskDist['depression']['safe']++;
                }
            } else {
                $riskDist['depression']['belum_skrining']++;
            }

            $hasStuntingHigh = false;
            if (isset($latestStuntingByUser[$uid])) {
                $st = $latestStuntingByUser[$uid];
                $isHigh = ((int) $st->prediction === 1) || strtolower((string) $st->risk_label) === 'high_risk';
                if ($isHigh) {
                    $riskDist['stunting']['tinggi']++;
                    $hasStuntingHigh = true;
                } else {
                    $riskDist['stunting']['rendah']++;
                }
            } else {
                $riskDist['stunting']['belum_skrining']++;
            }

            $isHighRiskIbu = $hasAnemia || $hasDepression || $hasStuntingHigh;
            if ($isHighRiskIbu) {
                $highRiskIbuTotal++;
            }

            if ($dCode && isset($districtStats[$dCode])) {
                $districtStats[$dCode]['ibu_hamil_count']++;
                if (isset($districtStats[$dCode][$trimesterKey])) {
                    $districtStats[$dCode][$trimesterKey]++;
                }
                if ($hasAnemia) {
                    $districtStats[$dCode]['risk_anemia']++;
                }
                if ($hasDepression) {
                    $districtStats[$dCode]['risk_depression']++;
                }
                if ($hasStuntingHigh) {
                    $districtStats[$dCode]['risk_stunting']++;
                }
                if ($isHighRiskIbu) {
                    $districtStats[$dCode]['high_risk_ibu_count']++;
                }
            }
        }

        // 5. Verified & active partner PMB in this regency
        $today = Carbon::today()->toDateString();
        $verifiedPmbs = DB::table('users')
            ->join('bidan_profile', 'bidan_profile.user_id', '=', 'users.user_id')
            ->where('users.role', 'bidan')
            ->where('users.is_active', true)
            ->where('bidan_profile.regency_code', $regencyCode)
            ->whereExists(function ($sub) use ($today) {
                $sub->select(DB::raw(1))
                    ->from('bidan_subscriptions')
                    ->join('bidan_applications', 'bidan_applications.bidan_application_id', '=', 'bidan_subscriptions.bidan_application_id')
                    ->whereColumn('bidan_subscriptions.user_id', 'users.user_id')
                    ->where('bidan_subscriptions.status', BidanSubscription::STATUS_ACTIVE)
                    ->whereDate('bidan_subscriptions.end_date', '>=', $today)
                    ->where('bidan_applications.status', BidanApplication::STATUS_APPROVED)
                    ->whereNotNull('bidan_applications.str_number')
                    ->where('bidan_applications.str_number', '!=', '')
                    ->whereNotNull('bidan_applications.sip_number')
                    ->where('bidan_applications.sip_number', '!=', '');
            })
            ->select(['users.user_id', 'bidan_profile.district_code'])
            ->get();

        foreach ($verifiedPmbs as $pmb) {
            if ($pmb->district_code && isset($districtStats[$pmb->district_code])) {
                $districtStats[$pmb->district_code]['pmb_count']++;
            }
        }

        $districtsCovered = collect($districtStats)
            ->filter(fn ($d) => $d['ibu_hamil_count'] > 0 || $d['pmb_count'] > 0)
            ->count();

        return response()->json([
            'status' => 'success',
            'data' => [
                'region' => [
                    'province_code' => $regency->province_code,
                    'province_name' => $regency->province?->name,
                    'regency_code' => $regency->code,
                    'regency_name' => $regency->name,
                ],
                'summary' => [
                    'total_ibu_hamil' => count($ibuUserIds),
                    'total_pmb_verified' => $verifiedPmbs->count(),
                    'total_districts' => $districts->count(),
                    'total_districts_covered' => $districtsCovered,
                    'ibu_hamil_high_risk' => $highRiskIbuTotal,
                ],
                'trimester_distribution' => $trimesterDist,
                'risk_distribution' => $riskDist,
                'by_district' => array_values($districtStats),
            ],
        ]);
    }

    /**
     * List Ibu Hamil and verified PMB partners scoped strictly to authenticated Dinkes Kab/Kota
     * GET /api/dinkes/users
     */
    public function users(Request $request): JsonResponse
    {
        [$userId, $role] = AuthToken::uidRoleOrFail($request);

        if ($role !== 'dinkes') {
            return response()->json([
                'status' => 'error',
                'message' => 'Akses ditolak: hanya akun Dinkes yang dapat mengakses data ini.',
            ], 403);
        }

        $dinkesProfile = UserDinkes::where('user_id', $userId)->first();

        if (!$dinkesProfile || empty($dinkesProfile->regency_code)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Akun Dinkes belum terhubung ke Kabupaten/Kota. Hubungi Admin Prenava untuk menetapkan wilayah.',
            ], 422);
        }

        $regency = Regency::with('province')->find($dinkesProfile->regency_code);
        if (!$regency) {
            return response()->json([
                'status' => 'error',
                'message' => 'Kode wilayah Kabupaten/Kota tidak ditemukan di master wilayah.',
            ], 422);
        }

        $regencyCode = $regency->code;

        $districts = District::where('regency_code', $regencyCode)
            ->orderBy('name')
            ->get(['code', 'name']);

        $districtNameByCode = $districts->pluck('name', 'code')->all();

        // Ibu Hamil in this regency
        $ibuRows = DB::table('users')
            ->join('user_profile', 'user_profile.user_id', '=', 'users.user_id')
            ->where('users.role', 'ibu_hamil')
            ->where('user_profile.regency_code', $regencyCode)
            ->orderBy('users.created_at', 'desc')
            ->select([
                'users.user_id',
                'users.name',
                'users.email',
                'users.is_active',
                'users.created_at',
                'user_profile.tanggal_lahir',
                'user_profile.usia',
                'user_profile.no_telepon',
                'user_profile.status_kandungan',
                'user_profile.district_code',
                'user_profile.kecamatan',
            ])
            ->get();

        $ibuUserIds = $ibuRows->pluck('user_id')->all();

        $pregnanciesByUser = [];
        $calculatorsByUser = [];
        $latestAnemiaByUser = [];
        $latestDepressionByUser = [];
        $latestStuntingByUser = [];

        if (!empty($ibuUserIds)) {
            $pregnancies = DB::table('pregnancies')
                ->whereIn('user_id', $ibuUserIds)
                ->where('status', 'ongoing')
                ->orderBy('created_at', 'desc')
                ->orderBy('pregnancy_id', 'desc')
                ->get(['user_id', 'lmp_date', 'gestational_age_weeks']);

            foreach ($pregnancies as $p) {
                if (!isset($pregnanciesByUser[$p->user_id])) {
                    $pregnanciesByUser[$p->user_id] = $p;
                }
            }

            $calculators = DB::table('pregnancy_calculators')
                ->whereIn('user_id', $ibuUserIds)
                ->orderBy('created_at', 'desc')
                ->orderBy('id', 'desc')
                ->get(['user_id', 'hpht']);

            foreach ($calculators as $c) {
                if (!isset($calculatorsByUser[$c->user_id])) {
                    $calculatorsByUser[$c->user_id] = $c;
                }
            }

            $scans = HealthScanHistory::whereIn('user_id', $ibuUserIds)
                ->orderBy('created_at', 'desc')
                ->orderBy('id', 'desc')
                ->get(['user_id', 'type', 'result']);

            foreach ($scans as $scan) {
                if ($scan->type === 'anemia' && !isset($latestAnemiaByUser[$scan->user_id])) {
                    $latestAnemiaByUser[$scan->user_id] = $scan->result;
                } elseif ($scan->type === 'depression' && !isset($latestDepressionByUser[$scan->user_id])) {
                    $latestDepressionByUser[$scan->user_id] = $scan->result;
                }
            }

            $stuntings = StuntingPrediction::whereIn('user_id', $ibuUserIds)
                ->where('status', StuntingPrediction::STATUS_SUCCESS)
                ->orderBy('created_at', 'desc')
                ->orderBy('id', 'desc')
                ->get(['user_id', 'prediction', 'risk_label']);

            foreach ($stuntings as $st) {
                if (!isset($latestStuntingByUser[$st->user_id])) {
                    $latestStuntingByUser[$st->user_id] = $st;
                }
            }
        }

        $trimesterLabels = [
            'trimester_1' => 'Trimester 1',
            'trimester_2' => 'Trimester 2',
            'trimester_3' => 'Trimester 3',
            'pasca_melahirkan' => 'Pasca Melahirkan',
            'belum_diisi' => 'Belum Diisi',
        ];

        $ibuHamilList = $ibuRows->map(function ($ibu) use (
            $districtNameByCode,
            $pregnanciesByUser,
            $calculatorsByUser,
            $latestAnemiaByUser,
            $latestDepressionByUser,
            $latestStuntingByUser,
            $trimesterLabels
        ) {
            $uid = $ibu->user_id;
            $trimesterKey = $this->resolveTrimester(
                $pregnanciesByUser[$uid] ?? null,
                $calculatorsByUser[$uid] ?? null,
                $ibu->status_kandungan
            );

            $anemiaStatus = 'belum_skrining';
            if (isset($latestAnemiaByUser[$uid])) {
                $payload = is_string($latestAnemiaByUser[$uid])
                    ? json_decode($latestAnemiaByUser[$uid], true)
                    : $latestAnemiaByUser[$uid];
                $label = strtolower(trim((string) ($payload['label'] ?? '')));
                if ($label === 'anemia') {
                    $anemiaStatus = 'berisiko';
                } elseif ($label === 'non-anemia' || $label === 'non anemia') {
                    $anemiaStatus = 'aman';
                }
            }

            $depressionStatus = 'belum_skrining';
            if (isset($latestDepressionByUser[$uid])) {
                $payload = is_string($latestDepressionByUser[$uid])
                    ? json_decode($latestDepressionByUser[$uid], true)
                    : $latestDepressionByUser[$uid];
                $level = strtolower((string) ($payload['level'] ?? $payload['depression_level'] ?? ''));
                $depressionStatus = (str_contains($level, 'indikator depresi') && !str_contains($level, 'tidak signifikan'))
                    ? 'berisiko'
                    : 'aman';
            }

            $stuntingStatus = 'belum_skrining';
            if (isset($latestStuntingByUser[$uid])) {
                $st = $latestStuntingByUser[$uid];
                $isHigh = ((int) $st->prediction === 1) || strtolower((string) $st->risk_label) === 'high_risk';
                $stuntingStatus = $isHigh ? 'berisiko' : 'aman';
            }

            $age = $ibu->usia !== null
                ? (int) $ibu->usia
                : (!empty($ibu->tanggal_lahir) ? Carbon::parse($ibu->tanggal_lahir)->age : null);

            return [
                'user_id' => $uid,
                'name' => $ibu->name,
                'email' => $ibu->email,
                'is_active' => (bool) $ibu->is_active,
                'age' => $age,
                'phone' => $ibu->no_telepon,
                'district_code' => $ibu->district_code,
                'district_name' => ($ibu->district_code && isset($districtNameByCode[$ibu->district_code]))
                    ? $districtNameByCode[$ibu->district_code]
                    : ($ibu->kecamatan ?: '-'),
                'trimester' => $trimesterLabels[$trimesterKey] ?? 'Belum Diisi',
                'risk_anemia' => $anemiaStatus,
                'risk_depression' => $depressionStatus,
                'risk_stunting' => $stuntingStatus,
                'is_high_risk' => $anemiaStatus === 'berisiko' || $depressionStatus === 'berisiko' || $stuntingStatus === 'berisiko',
                'created_at' => $ibu->created_at,
            ];
        })->values();

        // Verified PMB partners in this regency
        $today = Carbon::today()->toDateString();
        $bidanRows = DB::table('users')
            ->join('bidan_profile', 'bidan_profile.user_id', '=', 'users.user_id')
            ->where('users.role', 'bidan')
            ->where('bidan_profile.regency_code', $regencyCode)
            ->orderBy('users.created_at', 'desc')
            ->select([
                'users.user_id',
                'users.name',
                'users.email',
                'users.is_active',
                'users.created_at',
                'bidan_profile.tempat_praktik',
                'bidan_profile.alamat_praktik',
                'bidan_profile.district_code',
                'bidan_profile.kecamatan_tempat_praktik',
                'bidan_profile.telepon_tempat_praktik',
                'bidan_profile.spesialisasi',
                'bidan_profile.str_number',
                'bidan_profile.sip_number',
                'bidan_profile.is_verified_partner',
            ])
            ->get();

        $activeSubUserIds = DB::table('bidan_subscriptions')
            ->where('status', BidanSubscription::STATUS_ACTIVE)
            ->whereDate('end_date', '>=', $today)
            ->pluck('user_id')
            ->flip()
            ->all();

        $bidansList = $bidanRows->map(function ($b) use ($districtNameByCode, $activeSubUserIds) {
            $hasActiveSub = isset($activeSubUserIds[$b->user_id]);
            $hasLegalDocs = !empty($b->str_number) && !empty($b->sip_number);

            return [
                'user_id' => $b->user_id,
                'name' => $b->name,
                'email' => $b->email,
                'is_active' => (bool) $b->is_active,
                'tempat_praktik' => $b->tempat_praktik,
                'alamat_praktik' => $b->alamat_praktik,
                'district_code' => $b->district_code,
                'district_name' => ($b->district_code && isset($districtNameByCode[$b->district_code]))
                    ? $districtNameByCode[$b->district_code]
                    : ($b->kecamatan_tempat_praktik ?: '-'),
                'phone' => $b->telepon_tempat_praktik,
                'spesialisasi' => $b->spesialisasi,
                'str_number' => $b->str_number,
                'sip_number' => $b->sip_number,
                'is_verified_partner' => (bool) $b->is_verified_partner || ($hasActiveSub && $hasLegalDocs),
                'has_active_subscription' => $hasActiveSub,
                'created_at' => $b->created_at,
            ];
        })->values();

        return response()->json([
            'status' => 'success',
            'data' => [
                'region' => [
                    'province_code' => $regency->province_code,
                    'province_name' => $regency->province?->name,
                    'regency_code' => $regency->code,
                    'regency_name' => $regency->name,
                ],
                'districts' => $districts,
                'ibu_hamil' => $ibuHamilList,
                'bidans' => $bidansList,
            ],
        ]);
    }

    private function resolveTrimester(?object $pregnancy, ?object $calculator, ?string $statusKandungan): string
    {
        $weeks = null;

        if ($pregnancy) {
            if (!empty($pregnancy->lmp_date)) {
                $weeks = (int) floor(Carbon::parse($pregnancy->lmp_date)->diffInDays(now()) / 7);
            } elseif ($pregnancy->gestational_age_weeks !== null) {
                $weeks = (int) $pregnancy->gestational_age_weeks;
            }
        }

        if ($weeks === null && $calculator) {
            if (!empty($calculator->hpht)) {
                $weeks = (int) floor(Carbon::parse($calculator->hpht)->diffInDays(now()) / 7);
            }
        }

        if ($weeks !== null && $weeks >= 0 && $weeks <= 45) {
            if ($weeks <= 12) {
                return 'trimester_1';
            }
            if ($weeks <= 27) {
                return 'trimester_2';
            }
            return 'trimester_3';
        }

        $normalized = strtolower(trim((string) $statusKandungan));
        if (str_contains($normalized, 'trimester 1') || $normalized === '1') {
            return 'trimester_1';
        }
        if (str_contains($normalized, 'trimester 2') || $normalized === '2') {
            return 'trimester_2';
        }
        if (str_contains($normalized, 'trimester 3') || $normalized === '3') {
            return 'trimester_3';
        }
        if (str_contains($normalized, 'pasca') || str_contains($normalized, 'postpartum')) {
            return 'pasca_melahirkan';
        }

        return 'belum_diisi';
    }
}
