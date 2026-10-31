<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Models\FoodScan;
use App\Models\Wallet;
use App\Models\Pregnancy;
use App\Models\ActivityLog;
use App\Services\ActivityLogService;
use App\Services\MaternalTriageService;

class FoodScanController extends Controller
{
    /**
     * Menganalisis foto piring makan ibu hamil via ML Microservice YOLOv8 & Evaluator Gizi Kemenkes.
     * Endpoint: POST /api/v1/food-scanner/analyze atau POST /api/food-scan
     */
    public function scan(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'image' => 'required|file|mimes:jpg,jpeg,png,webp,bmp|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Validasi gagal: Harap upload citra piring makanan yang valid (JPG/PNG, maks 10MB).',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $file = $request->file('image');

        // Resolusi URL Microservice ML Food Vision (Port 9002)
        $rawUrl = config('services.ml.food', env('URL_ML_FOOD', 'http://127.0.0.1:9002'));
        if (file_exists('/.dockerenv')) {
            $rawUrl = str_replace(['127.0.0.1', 'localhost'], 'host.docker.internal', $rawUrl);
        }
        $mlEndpoint = rtrim($rawUrl, '/') . '/predict-food';

        try {
            // Forward citra multipart ke FastAPI Microservice dengan Content-Type & ekstensi yang valid
            $mimeType = $file->getMimeType() ?: 'image/jpeg';
            $fileName = $file->getClientOriginalName() ?: 'food_plate.jpg';
            if (!preg_match('/\.(jpg|jpeg|png|webp|bmp)$/i', $fileName)) {
                $ext = $file->guessExtension() ?: 'jpg';
                $fileName = rtrim($fileName, '.') . '.' . $ext;
            }

            $response = Http::timeout(45)
                ->attach(
                    'file',
                    file_get_contents($file->getRealPath()),
                    $fileName,
                    ['Content-Type' => $mimeType]
                )
                ->post($mlEndpoint);

            if (! $response->ok()) {
                $errJson = $response->json();
                $errDetail = is_array($errJson)
                    ? ($errJson['detail'] ?? $errJson['message'] ?? 'Microservice ML Food Vision mengembalikan kesalahan.')
                    : 'Microservice ML Food Vision mengembalikan status non-200.';

                return response()->json([
                    'status'          => 'error',
                    'message'         => $errDetail,
                    'upstream_status' => $response->status(),
                ], 422);
            }

            $mlData = $response->json();
            $summary = $mlData['summary'] ?? [];
            $plateAnalysis = $mlData['plate_analysis'] ?? [];
            $detectedItems = $mlData['detected_items'] ?? [];

            // Simpan foto piring makan secara lokal / public storage
            $savedImagePath = null;
            try {
                $savedImagePath = $file->store('food_scans', 'public');
            } catch (\Exception $e) {
                Log::warning("Gagal menyimpan fisik foto piring: " . $e->getMessage());
            }

            // Identifikasi pengguna & kehamilan aktif
            $user = $request->user();
            $userId = $user ? ($user->user_id ?? $user->id) : null;
            $pregnancyId = null;

            if ($userId) {
                $activePregnancy = Pregnancy::where('user_id', $userId)
                    ->where('status', 'ongoing')
                    ->latest()
                    ->first()
                    ?? Pregnancy::where('user_id', $userId)->latest()->first();

                $pregnancyId = $activePregnancy?->pregnancy_id;
            }

            $nutritionScore = (int) ($plateAnalysis['nutrition_score'] ?? 0);
            $balanceStatus = (string) ($plateAnalysis['balance_status'] ?? 'KURANG_SEIMBANG');
            $hasIron = (bool) ($plateAnalysis['has_iron_protein'] ?? false);
            $hasVeggie = (bool) ($plateAnalysis['has_folate_vegetable'] ?? false);
            $hasFruit = (bool) ($plateAnalysis['has_fruit'] ?? false);
            $hasCarbo = (bool) ($plateAnalysis['has_carbohydrate'] ?? false);

            // 1. Catat ke tabel database food_scans
            $foodScan = null;
            $coinRewarded = false;
            $coinAmount = 0.00;

            if ($userId) {
                // Evaluasi Reward Gamifikasi Koin (+50 Koin Dinkes jika piring SEIMBANG / CUKUP_SEIMBANG - PRD FR-07)
                if (in_array($balanceStatus, ['SEIMBANG', 'CUKUP_SEIMBANG'])) {
                    try {
                        $wallet = Wallet::firstOrCreate(
                            ['user_id' => $userId],
                            ['wallet_ad' => 0.00, 'wallet_dinkes' => 0.00]
                        );
                        $wallet->creditDinkes(50.00);
                        $coinRewarded = true;
                        $coinAmount = 50.00;
                    } catch (\Exception $e) {
                        Log::error("Gagal menambahkan reward koin nutrisi: " . $e->getMessage());
                    }
                }

                $foodScan = FoodScan::create([
                    'user_id'                    => $userId,
                    'pregnancy_id'               => $pregnancyId,
                    'image_path'                 => $savedImagePath,
                    'nutrition_score'            => $nutritionScore,
                    'balance_status'             => $balanceStatus,
                    'has_iron_protein'           => $hasIron,
                    'has_folate_vegetable'       => $hasVeggie,
                    'has_fruit'                  => $hasFruit,
                    'has_carbohydrate'           => $hasCarbo,
                    'total_items_detected'       => (int) ($summary['total_items_detected'] ?? count($detectedItems)),
                    'detected_items'             => $detectedItems,
                    'proportion_breakdown'       => $plateAnalysis['proportion_breakdown'] ?? [],
                    'clinical_feedback'          => $plateAnalysis['clinical_feedback'] ?? null,
                    'actionable_recommendations' => $plateAnalysis['actionable_recommendations'] ?? [],
                    'coin_rewarded'              => $coinRewarded,
                    'coin_amount'                => $coinAmount,
                ]);

                // 2. Audit Trail Activity Log
                try {
                    ActivityLogService::log(
                        ActivityLog::TYPE_SCAN_PIRING_GIZI,
                        $userId,
                        $user->name ?? 'Pasien',
                        $user->email ?? null,
                        $user->role ?? 'user',
                        "Melakukan pemindaian piring gizi seimbang 'Isi Piringku' (Skor: {$nutritionScore}/100 - {$balanceStatus})",
                        [
                            'food_scan_id'    => $foodScan->food_scan_id,
                            'nutrition_score' => $nutritionScore,
                            'balance_status'  => $balanceStatus,
                            'has_iron'        => $hasIron,
                            'coin_rewarded'   => $coinRewarded,
                        ],
                        $request
                    );
                } catch (\Exception $e) {
                    Log::warning("Gagal membuat activity log scan piring: " . $e->getMessage());
                }

                // 3. Rekalkulasi Triase Klinis Maternal CDSS (Pilar ke-4 Kepatuhan Piring Nutrisi)
                try {
                    $triageService = app(MaternalTriageService::class);
                    $triageService->calculatePatientTriage($userId, $pregnancyId);
                } catch (\Exception $e) {
                    Log::warning("Gagal memperbarui Maternal Triage Score: " . $e->getMessage());
                }
            }

            return response()->json([
                'status'         => 'success',
                'message'        => 'Analisis gizi piring makan berhasil.',
                'food_scan_id'   => $foodScan?->food_scan_id,
                'image_url'      => $savedImagePath ? asset('storage/' . $savedImagePath) : null,
                'coin_reward'    => [
                    'awarded' => $coinRewarded,
                    'amount'  => $coinAmount,
                    'message' => $coinRewarded ? 'Selamat! Anda memperoleh +50 Koin Nutrisi Seimbang.' : null,
                ],
                'summary'        => $summary,
                'detected_items' => $detectedItems,
                'plate_analysis' => $plateAnalysis,
            ], 200);

        } catch (\Illuminate\Http\Client\RequestException $re) {
            $errResponse = $re->response;
            $errJson = $errResponse ? $errResponse->json() : null;
            $detail = is_array($errJson) ? ($errJson['detail'] ?? $errJson['message'] ?? $re->getMessage()) : $re->getMessage();
            Log::error("ML Food Vision HTTP Error: " . $detail);
            return response()->json([
                'status'  => 'error',
                'message' => 'Layanan ML Food Vision: ' . $detail,
            ], 422);
        } catch (\Exception $e) {
            Log::error("Koneksi gagal ke ML Food Vision: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'status'  => 'error',
                'message' => 'Layanan AI Food Scanner sedang tidak dapat dijangkau. Pastikan microservice Port 9002 aktif.',
                'error'   => $e->getMessage(),
            ], 503);
        }
    }

    /**
     * Mengambil riwayat pemindaian piring makan pasien
     * Endpoint: GET /api/v1/food-scanner/history atau GET /api/food-scan/history
     */
    public function history(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Harap login untuk melihat riwayat scan makanan.',
            ], 401);
        }

        $userId = $request->query('user_id', $user->user_id ?? $user->id);

        // Jika bidan meminta data pasien lain diperbolehkan, jika pasien biasa hanya boleh melihat data sendiri
        if (($user->role ?? 'user') !== 'bidan' && ($user->role ?? 'user') !== 'admin') {
            $userId = $user->user_id ?? $user->id;
        }

        $scans = FoodScan::where('user_id', $userId)
            ->latest('created_at')
            ->paginate($request->query('per_page', 15));

        return response()->json([
            'status' => 'success',
            'data'   => $scans,
        ]);
    }

    /**
     * Mengambil detail hasil pemindaian spesifik
     * Endpoint: GET /api/v1/food-scanner/history/{id} atau GET /api/food-scan/history/{id}
     */
    public function show($id): JsonResponse
    {
        $scan = FoodScan::find($id);
        if (! $scan) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Data scan makanan tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data'   => $scan,
        ]);
    }
}
