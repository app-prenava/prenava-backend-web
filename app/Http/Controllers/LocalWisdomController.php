<?php

namespace App\Http\Controllers;

use App\Models\LocalWisdom;
use App\Models\UserWisdomLog;
use Illuminate\Http\Request;
use Carbon\Carbon;

class LocalWisdomController extends Controller
{
    /**
     * Ambil semua mitos beserta status apakah user sudah centang hari ini.
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $today = Carbon::today()->toDateString();

        $myths = LocalWisdom::all();
        
        $userLogs = UserWisdomLog::where('user_id', $user->user_id)
            ->whereDate('checked_date', $today)
            ->pluck('local_wisdom_id')
            ->toArray();

        $data = $myths->map(function ($myth) use ($userLogs) {
            return [
                'id' => $myth->id,
                'myth' => $myth->myth,
                'reason' => $myth->reason,
                'region' => $myth->region,
                'is_checked' => in_array($myth->id, $userLogs)
            ];
        });

        return response()->json($data);
    }

    /**
     * Simpan/Log ketika user mencentang mitos (interaksi).
     */
    public function toggle(Request $request)
    {
        $request->validate([
            'local_wisdom_id' => 'required|exists:local_wisdom,id'
        ]);

        $user = auth()->user();
        $today = Carbon::today()->toDateString();

        $existing = UserWisdomLog::where('user_id', $user->user_id)
            ->where('local_wisdom_id', $request->local_wisdom_id)
            ->whereDate('checked_date', $today)
            ->first();

        if ($existing) {
            $existing->delete();
            return response()->json([
                'message' => 'Wisdom unchecked',
                'is_checked' => false
            ]);
        }

        UserWisdomLog::create([
            'user_id' => $user->user_id,
            'local_wisdom_id' => $request->local_wisdom_id,
            'checked_date' => $today
        ]);

        return response()->json([
            'message' => 'Wisdom checked',
            'is_checked' => true
        ]);
    }

    /**
     * Analytics: Mitos yang paling banyak dibaca/dicentang (Untuk Tugas Akhir).
     */
    public function analytics()
    {
        $analytics = LocalWisdom::withCount('userLogs')
            ->orderBy('user_logs_count', 'desc')
            ->get();

        return response()->json($analytics);
    }
}
