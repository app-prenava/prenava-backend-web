<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Models\HealthScanHistory;
use App\Models\User;
use App\Models\ActivityLog;
use App\Services\ActivityLogService;

class ProcessAnemiaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $jobId;
    public ?int $userId;
    public string $tempFilePath;
    public string $originalFilename;

    public int $tries = 3;
    public int $timeout = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(string $jobId, ?int $userId, string $tempFilePath, string $originalFilename)
    {
        $this->jobId            = $jobId;
        $this->userId           = $userId;
        $this->tempFilePath     = $tempFilePath;
        $this->originalFilename = $originalFilename;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info("ProcessAnemiaJob [{$this->jobId}]: Memulai eksekusi inferensi AI latar belakang.");

        // Update progress di Redis Cache: 40%
        Cache::put("ai_job:{$this->jobId}", [
            'status'     => 'processing',
            'progress'   => 40,
            'job_id'     => $this->jobId,
            'message'    => 'Menghubungi layanan DL Anemia MobileNetV2 untuk ekstraksi ROI...',
            'updated_at' => now()->toIso8601String(),
        ], now()->addHours(24));

        if (! file_exists($this->tempFilePath)) {
            $errorMsg = "Berkas citra sementara tidak ditemukan di: {$this->tempFilePath}";
            Log::error("ProcessAnemiaJob [{$this->jobId}]: {$errorMsg}");
            $this->markFailed($errorMsg);
            return;
        }

        // Tentukan URL ML service
        $rawUrl = config('services.ml.anemia', env('URL_ML_ANEMIA', 'http://127.0.0.1:9001/'));
        if (file_exists('/.dockerenv')) {
            $rawUrl = str_replace(['127.0.0.1', 'localhost'], 'host.docker.internal', $rawUrl);
        }
        $mlUrl = rtrim($rawUrl, '/') . '/predict';

        try {
            // Update progress di Redis Cache: 70%
            Cache::put("ai_job:{$this->jobId}", [
                'status'     => 'processing',
                'progress'   => 70,
                'job_id'     => $this->jobId,
                'message'    => 'Menganalisis tingkat kepucatan konjungtiva mata...',
                'updated_at' => now()->toIso8601String(),
            ], now()->addHours(24));

            $response = Http::timeout(30)
                ->retry(2, 500)
                ->attach(
                    'file',
                    file_get_contents($this->tempFilePath),
                    $this->originalFilename
                )
                ->post($mlUrl);

            if (! $response->ok()) {
                $errJson = $response->json();
                $errDetail = is_array($errJson)
                    ? ($errJson['detail'] ?? $errJson['message'] ?? 'ML Anemia Service error.')
                    : 'ML Anemia Service error.';

                Log::error("ProcessAnemiaJob [{$this->jobId}]: ML upstream error - {$errDetail}");
                $this->markFailed($errDetail);
                $this->cleanupFile();
                return;
            }

            $mlResult = $response->json();

            // Simpan ke riwayat rekam medis pasien jika ada user_id
            $historyEntry = null;
            if ($this->userId) {
                try {
                    $historyEntry = HealthScanHistory::create([
                        'user_id' => $this->userId,
                        'type'    => 'anemia',
                        'result'  => $mlResult,
                    ]);

                    $user = User::find($this->userId);
                    if ($user) {
                        ActivityLogService::log(
                            ActivityLog::TYPE_DETEKSI_ANEMIA,
                            $user->user_id,
                            $user->name,
                            $user->email,
                            $user->role,
                            "User {$user->name} berhasil melakukan deteksi anemia asinkron (Redis Queue).",
                            ['job_id' => $this->jobId, 'prediction' => $mlResult]
                        );
                    }

                    // Rekalkulasi Triase Klinis Maternal CDSS (Pilar ke-2 Anemia Konjungtiva Mata)
                    try {
                        $triageService = app(\App\Services\MaternalTriageService::class);
                        $triageService->calculatePatientTriage($this->userId);
                    } catch (\Throwable $e) {
                        Log::warning("ProcessAnemiaJob [{$this->jobId}]: Gagal memperbarui Maternal Triage: " . $e->getMessage());
                    }
                } catch (\Throwable $e) {
                    Log::error("ProcessAnemiaJob [{$this->jobId}]: Gagal menyimpan history: " . $e->getMessage());
                }
            }

            // Update status akhir di Redis: COMPLETED (100%)
            Cache::put("ai_job:{$this->jobId}", [
                'status'       => 'completed',
                'progress'     => 100,
                'job_id'       => $this->jobId,
                'message'      => 'Inferensi selesai.',
                'data'         => $mlResult,
                'history'      => $historyEntry ? [
                    'id'         => $historyEntry->id,
                    'type'       => $historyEntry->type,
                    'created_at' => $historyEntry->created_at,
                ] : null,
                'completed_at' => now()->toIso8601String(),
            ], now()->addHours(24));

            Log::info("ProcessAnemiaJob [{$this->jobId}]: Sukses diproses.");

        } catch (\Throwable $e) {
            Log::error("ProcessAnemiaJob [{$this->jobId}]: Exception - " . $e->getMessage());
            $this->markFailed($e->getMessage());
        } finally {
            $this->cleanupFile();
        }
    }

    /**
     * Handle kegagalan job
     */
    public function failed(?\Throwable $exception): void
    {
        $error = $exception ? $exception->getMessage() : 'Gagal mengeksekusi job antrean.';
        Log::error("ProcessAnemiaJob [{$this->jobId}] FAILED: {$error}");
        $this->markFailed($error);
        $this->cleanupFile();
    }

    private function markFailed(string $error): void
    {
        Cache::put("ai_job:{$this->jobId}", [
            'status'    => 'failed',
            'progress'  => 0,
            'job_id'    => $this->jobId,
            'error'     => $error,
            'failed_at' => now()->toIso8601String(),
        ], now()->addHours(24));
    }

    private function cleanupFile(): void
    {
        if (file_exists($this->tempFilePath)) {
            @unlink($this->tempFilePath);
        }
    }
}
