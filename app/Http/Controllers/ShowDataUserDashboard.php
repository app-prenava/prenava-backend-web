<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ShowDataUserDashboard extends Controller
{
    public function showDataAgeLmp(): JsonResponse
    {
        $rows = DB::table('pregnancies as p')
            ->join('user_profile as up', 'up.user_id', '=', 'p.user_id')
            ->select('p.lmp_date', 'up.tanggal_lahir')
            ->where('p.status', 'ongoing')
            ->whereNotNull('p.lmp_date')
            ->whereNotNull('up.tanggal_lahir')
            ->get();

        $gestationalCounts = []; 
        $ageCounts         = [];

        foreach ($rows as $row) {
            $weeks = (int) round(
                Carbon::parse($row->lmp_date)->diffInDays(now()) / 7
            );

            if ($weeks >= 0 && $weeks <= 41) {
                $gestationalCounts[$weeks] = ($gestationalCounts[$weeks] ?? 0) + 1;
            }

            $age = Carbon::parse($row->tanggal_lahir)->age;
            $ageCounts[$age] = ($ageCounts[$age] ?? 0) + 1;
        }

        ksort($gestationalCounts);
        ksort($ageCounts);

        $gestationalDistribution = [];
        foreach ($gestationalCounts as $week => $count) {
            $gestationalDistribution[] = [
                'gestational_age_weeks' => $week,
                'total'                 => $count,
            ];
        }

        $ageDistribution = [];
        foreach ($ageCounts as $age => $count) {
            $ageDistribution[] = [
                'age'   => $age,
                'total' => $count,
            ];
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Distribusi usia kehamilan dan usia ibu.',
            'data'    => [
                'gestational_age_distribution' => $gestationalDistribution,
                'age_distribution'             => $ageDistribution,
            ],
        ], 200);
    }
}