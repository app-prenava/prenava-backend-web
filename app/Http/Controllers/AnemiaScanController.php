<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\ActivityLog;
use App\Services\ActivityLogService;
use App\Jobs\ProcessAnemiaJob;

class AnemiaScanController extends Controller
{
    public function scan(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'image' => 'required|file|mimes:jpg,jpeg,png,webp,bmp|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Validation failed.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $file = $request->file('image');

        $rawUrl = config('services.ml.anemia', env('URL_ML_ANEMIA', 'http://72.61.213.163:9001/'));
        if (file_exists('/.dockerenv')) {
            $rawUrl = str_replace(['127.0.0.1', 'localhost'], 'host.docker.internal', $rawUrl);
        }
        $mlUrl = rtrim($rawUrl, '/') . '/predict';

        try {
            $response = Http::timeout(30)
                ->retry(2, 500)
                ->attach(
                    'file',
                    file_get_contents($file->getRealPath()),
                    $file->getClientOriginalName()
                )
                ->post($mlUrl);

            if (! $response->ok()) {
                $errJson = $response->json();
                $errDetail = is_array($errJson)
                    ? ($errJson['detail'] ?? $errJson['message'] ?? 'ML service returned an error.')
                    : 'ML service returned an error.';

                return response()->json([
                    'status'  => 'error',
                    'message' => $errDetail,
                    'upstream_status' => $response->status(),
                ], 422);
            }

            $ml = $response->json();

            // Log deteksi anemia (scan foto)
            $historyEntry = null;
            if ($user = $request->user()) {
                try {
                    // Save to History table
                    $historyEntry = \App\Models\HealthScanHistory::create([
                        'user_id' => $user->user_id ?? $user->id,
                        'type'    => 'anemia',
                        'result'  => $ml,
                    ]);
                } catch (\Exception $e) {
                    // Fail silently for history logging
                    \Illuminate\Support\Facades\Log::error("Failed to save anemia history: " . $e->getMessage());
                }

                ActivityLogService::logFromUser(
                    ActivityLog::TYPE_DETEKSI_ANEMIA,
                    $user,
                    "User {$user->name} melakukan deteksi anemia melalui scan mata/wajah.",
                    ['prediction' => $ml],
                    $request
                );

                // Rekalkulasi Triase Klinis Maternal CDSS (Pilar ke-2 Anemia Konjungtiva Mata)
                try {
                    $triageService = app(\App\Services\MaternalTriageService::class);
                    $triageService->calculatePatientTriage($user->user_id ?? $user->id);
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning("Gagal memperbarui Maternal Triage Score pasca deteksi anemia: " . $e->getMessage());
                }
            }

            return response()->json([
                'status' => 'success',
                'data'   => $ml,
                // FE can store image locally using this history id.
                // Backend intentionally does not store uploaded image path.
                'history' => $historyEntry ? [
                    'id'         => $historyEntry->id,
                    'type'       => $historyEntry->type,
                    'created_at' => $historyEntry->created_at,
                ] : null,
            ]);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Anemia Scan Exception: " . $e->getMessage());
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to connect to ML service.',
                'error_detail' => $e->getMessage(),
            ], 502);
        }
    }

    /**
     * Endpoint Asinkron: Mengunggah gambar, menjadwalkan ke Redis Queue,
     * dan langsung mengembalikan job_id secara non-blocking (< 200ms).
     * POST /api/v2/anemia-scan/async
     */
    public function scanAsync(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'image' => 'required|file|mimes:jpg,jpeg,png,webp,bmp|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Validasi citra gagal.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $file = $request->file('image');
        $jobId = (string) Str::uuid();

        // Simpan file sementara di storage lokal
        $ext = $file->getClientOriginalExtension() ?: 'jpg';
        $fileName = "{$jobId}.{$ext}";
        $relPath = $file->storeAs('temp_uploads/anemia', $fileName, 'local');
        $fullPath = Storage::disk('local')->path($relPath);

        // Inisialisasi status pekerjaan awal di Redis Cache (TTL: 24 jam)
        Cache::put("ai_job:{$jobId}", [
            'status'      => 'processing',
            'progress'    => 15,
            'job_id'      => $jobId,
            'message'     => 'Citra diterima dan dijadwalkan ke antrean Redis AI.',
            'created_at'  => now()->toIso8601String(),
        ], now()->addHours(24));

        // Ambil ID user jika terotentikasi (via JWT Bearer Token)
        $user = auth('api')->user() ?: $request->user();
        $userId = $user ? ($user->user_id ?? $user->id) : null;

        // Dispatch background job ke Redis Queue
        ProcessAnemiaJob::dispatch(
            $jobId,
            $userId,
            $fullPath,
            $file->getClientOriginalName()
        );

        return response()->json([
            'status'      => 'processing',
            'job_id'      => $jobId,
            'message'     => 'Pemeriksaan anemia sedang diproses di antrean AI latar belakang.',
            'polling_url' => "/api/v2/jobs/{$jobId}/status",
        ], 202);
    }

    /**
     * Endpoint Polling Status: Mengecek progres atau hasil akhir pekerjaan AI.
     * GET /api/v2/jobs/{jobId}/status
     */
    public function checkJobStatus(string $jobId): JsonResponse
    {
        $jobData = Cache::get("ai_job:{$jobId}");

        if (! $jobData) {
            return response()->json([
                'status'  => 'not_found',
                'message' => 'Tiket pekerjaan AI tidak ditemukan atau sudah kedaluwarsa.',
                'job_id'  => $jobId,
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'job'    => $jobData,
        ], 200);
    }
}
