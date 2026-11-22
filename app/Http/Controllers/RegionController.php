<?php

namespace App\Http\Controllers;

use App\Models\District;
use App\Models\Province;
use App\Models\Regency;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class RegionController extends Controller
{
    private const CACHE_TTL_SECONDS = 86400; // 24 hours

    public function provinces(): JsonResponse
    {
        $provinces = Cache::remember('regions:provinces', self::CACHE_TTL_SECONDS, function () {
            return Province::orderBy('name')->get(['code', 'name']);
        });

        return response()->json([
            'status' => 'success',
            'data' => $provinces,
        ]);
    }

    public function regencies(Request $request): JsonResponse
    {
        $provinceCode = trim((string) $request->query('province_code', ''));
        if ($provinceCode === '') {
            return response()->json([
                'status' => 'error',
                'message' => 'Parameter province_code wajib diisi',
            ], 422);
        }

        $regencies = Cache::remember("regions:regencies:{$provinceCode}", self::CACHE_TTL_SECONDS, function () use ($provinceCode) {
            return Regency::where('province_code', $provinceCode)
                ->orderBy('name')
                ->get(['code', 'province_code', 'name']);
        });

        return response()->json([
            'status' => 'success',
            'data' => $regencies,
        ]);
    }

    public function districts(Request $request): JsonResponse
    {
        $regencyCode = trim((string) $request->query('regency_code', ''));
        if ($regencyCode === '') {
            return response()->json([
                'status' => 'error',
                'message' => 'Parameter regency_code wajib diisi',
            ], 422);
        }

        $districts = Cache::remember("regions:districts:{$regencyCode}", self::CACHE_TTL_SECONDS, function () use ($regencyCode) {
            return District::where('regency_code', $regencyCode)
                ->orderBy('name')
                ->get(['code', 'regency_code', 'name']);
        });

        return response()->json([
            'status' => 'success',
            'data' => $districts,
        ]);
    }
}

