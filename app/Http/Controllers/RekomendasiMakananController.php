<?php

namespace App\Http\Controllers;

use App\Models\RekomendasiMakanan;
use App\Models\ActivityLog;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RekomendasiMakananController extends Controller
{
    public function index(Request $request)
    {
        // Ambil semua rekomendasi makanan
        $rekomendasi = RekomendasiMakanan::all();

        // Log rekomendasi makanan
        if ($user = Auth::user()) {
            ActivityLogService::logFromUser(
                ActivityLog::TYPE_REKOMENDASI_MAKANAN,
                $user,
                "User {$user->name} melihat daftar rekomendasi makanan.",
                request: $request
            );
        }

        return response()->json([
            'status' => 'success',
            'data' => $rekomendasi
        ]);
    }

    public function show($id)
    {
        // Ambil rekomendasi makanan berdasarkan ID
        $rekomendasi = RekomendasiMakanan::findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $rekomendasi
        ]);
    }
}
