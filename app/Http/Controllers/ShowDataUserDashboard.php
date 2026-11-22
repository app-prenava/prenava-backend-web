<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ShowDataUserDashboard extends Controller
{
    public function showDataAgeLmp(): JsonResponse
    {
        $ibuRows = DB::table('users as u')
            ->join('user_profile as up', 'up.user_id', '=', 'u.user_id')
            ->where('u.role', 'ibu_hamil')
            ->where('u.is_active', true)
            ->select('u.user_id', 'up.tanggal_lahir', 'up.usia', 'up.status_kandungan')
            ->get();

        $userIds = $ibuRows->pluck('user_id')->all();

        $pregnanciesByUser = [];
        $calculatorsByUser = [];

        if (!empty($userIds)) {
            $pregnancies = DB::table('pregnancies')
                ->whereIn('user_id', $userIds)
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
                ->whereIn('user_id', $userIds)
                ->orderBy('created_at', 'desc')
                ->orderBy('id', 'desc')
                ->get(['user_id', 'hpht']);

            foreach ($calculators as $c) {
                if (!isset($calculatorsByUser[$c->user_id])) {
                    $calculatorsByUser[$c->user_id] = $c;
                }
            }
        }

        $gestationalCounts = [];
        $ageCounts = [];
        $trimesterCounts = [
            'Trimester 1' => 0,
            'Trimester 2' => 0,
            'Trimester 3' => 0,
            'Pasca Melahirkan' => 0,
        ];

        foreach ($ibuRows as $ibu) {
            // 1. Usia Ibu: hitung dari tanggal_lahir, fallback ke kolom usia
            $age = null;
            if (!empty($ibu->tanggal_lahir)) {
                $age = Carbon::parse($ibu->tanggal_lahir)->age;
            } elseif ($ibu->usia !== null && is_numeric($ibu->usia)) {
                $age = (int) $ibu->usia;
            }

            if ($age !== null && $age > 0 && $age < 100) {
                $ageCounts[$age] = ($ageCounts[$age] ?? 0) + 1;
            }

            // 2. Usia Kehamilan dalam minggu: pregnancies.lmp_date -> gestational_age_weeks -> pregnancy_calculators.hpht
            $weeks = null;
            $preg = $pregnanciesByUser[$ibu->user_id] ?? null;
            $calc = $calculatorsByUser[$ibu->user_id] ?? null;

            if ($preg) {
                if (!empty($preg->lmp_date)) {
                    $weeks = (int) round(Carbon::parse($preg->lmp_date)->diffInDays(now()) / 7);
                } elseif ($preg->gestational_age_weeks !== null) {
                    $weeks = (int) $preg->gestational_age_weeks;
                }
            }

            if ($weeks === null && $calc && !empty($calc->hpht)) {
                $weeks = (int) round(Carbon::parse($calc->hpht)->diffInDays(now()) / 7);
            }

            if ($weeks !== null && $weeks >= 0 && $weeks <= 42) {
                $gestationalCounts[$weeks] = ($gestationalCounts[$weeks] ?? 0) + 1;
                if ($weeks <= 12) {
                    $trimesterCounts['Trimester 1']++;
                } elseif ($weeks <= 27) {
                    $trimesterCounts['Trimester 2']++;
                } else {
                    $trimesterCounts['Trimester 3']++;
                }
            } else {
                // Fallback ke status_kandungan dari onboarding user_profile
                $status = strtolower(trim((string) ($ibu->status_kandungan ?? '')));
                if (str_contains($status, 'trimester 1') || $status === '1') {
                    $trimesterCounts['Trimester 1']++;
                } elseif (str_contains($status, 'trimester 2') || $status === '2') {
                    $trimesterCounts['Trimester 2']++;
                } elseif (str_contains($status, 'trimester 3') || $status === '3') {
                    $trimesterCounts['Trimester 3']++;
                } elseif (str_contains($status, 'pasca') || str_contains($status, 'postpartum')) {
                    $trimesterCounts['Pasca Melahirkan']++;
                }
            }
        }

        ksort($gestationalCounts);
        ksort($ageCounts);

        $gestationalDistribution = [];
        foreach ($gestationalCounts as $week => $count) {
            $gestationalDistribution[] = [
                'gestational_age_weeks' => $week,
                'total' => $count,
            ];
        }

        $ageDistribution = [];
        foreach ($ageCounts as $age => $count) {
            $ageDistribution[] = [
                'age' => $age,
                'total' => $count,
            ];
        }

        $trimesterDistribution = [];
        foreach ($trimesterCounts as $label => $count) {
            if ($count > 0) {
                $trimesterDistribution[] = [
                    'trimester' => $label,
                    'total' => $count,
                ];
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Distribusi usia kehamilan dan usia ibu.',
            'data' => [
                'gestational_age_distribution' => $gestationalDistribution,
                'trimester_distribution' => $trimesterDistribution,
                'age_distribution' => $ageDistribution,
                'total_ibu_hamil' => count($userIds),
            ],
        ], 200);
    }
}
