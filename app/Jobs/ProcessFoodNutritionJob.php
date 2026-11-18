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
use Illuminate\Support\Facades\Storage;
use App\Models\FoodScan;
use App\Models\Wallet;
use App\Models\Pregnancy;
use App\Models\User;
use App\Models\ActivityLog;
use App\Services\ActivityLogService;
use App\Services\MaternalTriageService;

class ProcessFoodNutritionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $jobId;
    public ?int $userId;
    public string $tempFilePath;
    public string $originalFilename;
    public ?string $publicImagePath;

    public int $tries = 3;
    public int $timeout = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(
        string $jobId,
        ?int $userId,
        string $tempFilePath,
        string $originalFilename,
        ?string $publicImagePath = null
    ) {
        $this->jobId            = $jobId;
        $this->userId           = $userId;
        $this->tempFilePath     = $tempFilePath;
        $this->originalFilename = $originalFilename;
        $this->publicImagePath  = $publicImagePath;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info("ProcessFoodNutritionJob [{$this->jobId}]: Memulai eksekusi segmentasi YOLOv8 latar belakang.");

        // Update progress di Redis Cache: 40%
        Cache::put("ai_job:{$this->jobId}", [
            'status'     => 'processing',
            'progress'   => 40,
            'job_id'     => $this->jobId,
            'type'       => 'food_nutrition',
            'message'    => 'Menghubungi layanan ML Food Vision (YOLOv8-seg) untuk segmentasi piring makanan...',
            'updated_at' => now()->toIso8601String(),
        ], now()->addHours(24));

        if (! file_exists($this->tempFilePath)) {
            $errorMsg = "Berkas citra piring sementara tidak ditemukan di: {$this->tempFilePath}";
            Log::error("ProcessFoodNutritionJob [{$this->jobId}]: {$errorMsg}");
            $this->markFailed($errorMsg);
            return;
        }

        // Resolusi URL Microservice ML Food Vision (Port 9002)
        $rawUrl = config('services.ml.food', env('URL_ML_FOOD', 'http://127.0.0.1:9002'));
        if (file_exists('/.dockerenv')) {
            $rawUrl = str_replace(['127.0.0.1', 'localhost'], 'host.docker.internal', $rawUrl);
        }
        $mlEndpoint = rtrim($rawUrl, '/') . '/predict-food';

        try {
            $fileName = $this->originalFilename ?: 'food_plate.jpg';
            if (! preg_match('/\.(jpg|jpeg|png|webp|bmp)$/i', $fileName)) {
                $fileName = rtrim($fileName, '.') . '.jpg';
            }

            $response = Http::timeout(45)
                ->retry(2, 500)
                ->attach(
                    'file',
                    file_get_contents($this->tempFilePath),
                    $fileName
                )
                ->post($mlEndpoint);

            if (! $response->ok()) {
                $errJson = $response->json();
                $errDetail = is_array($errJson)
                    ? ($errJson['detail'] ?? $errJson['message'] ?? 'Microservice ML Food Vision mengembalikan kesalahan.')
                    : 'Microservice ML Food Vision mengembalikan status non-200.';

                Log::error("ProcessFoodNutritionJob [{$this->jobId}]: ML upstream error - {$errDetail}");
                $this->markFailed($errDetail);
                return;
            }

            $mlData = $response->json();
            $summary = $mlData['summary'] ?? [];
            $plateAnalysis = $mlData['plate_analysis'] ?? [];
            $detectedItems = $mlData['detected_items'] ?? [];

            // Update progress di Redis Cache: 75%
            Cache::put("ai_job:{$this->jobId}", [
                'status'     => 'processing',
                'progress'   => 75,
                'job_id'     => $this->jobId,
                'type'       => 'food_nutrition',
                'message'    => 'Mengevaluasi proporsi 5 kelas nutrisi dan memperbarui skor triase maternal CDSS...',
                'updated_at' => now()->toIso8601String(),
            ], now()->addHours(24));

            $nutritionScore = (int) ($plateAnalysis['nutrition_score'] ?? 0);
            $balanceStatus = (string) ($plateAnalysis['balance_status'] ?? 'KURANG_SEIMBANG');
            $hasIron = (bool) ($plateAnalysis['has_iron_protein'] ?? false);
            $hasVeggie = (bool) ($plateAnalysis['has_folate_vegetable'] ?? false);
            $hasFruit = (bool) ($plateAnalysis['has_fruit'] ?? false);
            $hasCarbo = (bool) ($plateAnalysis['has_carbohydrate'] ?? false);

            $foodScan = null;
            $coinRewarded = false;
            $coinAmount = 0.00;
            $pregnancyId = null;

            if ($this->userId) {
                try {
                    $activePregnancy = Pregnancy::where('user_id', $this->userId)
                        ->where('status', 'ongoing')
                        ->latest()
                        ->first()
                        ?? Pregnancy::where('user_id', $this->userId)->latest()->first();

                    $pregnancyId = $activePregnancy?->pregnancy_id;

                    if (in_array($balanceStatus, ['SEIMBANG', 'CUKUP_SEIMBANG'])) {
                        try {
                            $wallet = Wallet::firstOrCreate(
                                ['user_id' => $this->userId],
                                ['wallet_ad' => 0.00, 'wallet_dinkes' => 0.00]
                            );
                            $wallet->creditDinkes(50.00);
                            $coinRewarded = true;
                            $coinAmount = 50.00;
                        } catch (\Throwable $e) {
                            Log::error("ProcessFoodNutritionJob [{$this->jobId}]: Gagal menambahkan reward koin: " . $e->getMessage());
                        }
                    }

                    $foodScan = FoodScan::create([
                        'user_id'                    => $this->userId,
                        'pregnancy_id'               => $pregnancyId,
                        'image_path'                 => $this->publicImagePath,
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

                    $user = User::find($this->userId);
                    if ($user) {
                        ActivityLogService::log(
                            ActivityLog::TYPE_SCAN_PIRING_GIZI,
                            $this->userId,
                            $user->name ?? 'Pasien',
                            $user->email ?? null,
                            $user->role ?? 'user',
                            "User {$user->name} melakukan pemindaian piring gizi asinkron (Skor: {$nutritionScore}/100 - {$balanceStatus})",
                            [
                                'job_id'          => $this->jobId,
                                'food_scan_id'    => $foodScan->food_scan_id,
                                'nutrition_score' => $nutritionScore,
                                'balance_status'  => $balanceStatus,
                            ]
                        );
                    }

                    // Rekalkulasi Triase Klinis Maternal CDSS (Pilar ke-4 Kepatuhan Piring Nutrisi)
                    try {
                        $triageService = app(MaternalTriageService::class);
                        $triageService->calculatePatientTriage($this->userId, $pregnancyId);
                    } catch (\Throwable $e) {
                        Log::warning("ProcessFoodNutritionJob [{$this->jobId}]: Gagal memperbarui Maternal Triage: " . $e->getMessage());
                    }
                } catch (\Throwable $e) {
                    Log::error("ProcessFoodNutritionJob [{$this->jobId}]: Gagal menyimpan riwayat food scan: " . $e->getMessage());
                }
            }

            // Update status akhir di Redis: COMPLETED (100%)
            Cache::put("ai_job:{$this->jobId}", [
                'status'       => 'completed',
                'progress'     => 100,
                'job_id'       => $this->jobId,
                'type'         => 'food_nutrition',
                'message'      => 'Analisis gizi piring makan berhasil.',
                'data'         => [
                    'food_scan_id'   => $foodScan?->food_scan_id,
                    'image_url'      => $this->publicImagePath ? asset('storage/' . $this->publicImagePath) : null,
                    'coin_reward'    => [
                        'awarded' => $coinRewarded,
                        'amount'  => $coinAmount,
                        'message' => $coinRewarded ? 'Selamat! Anda memperoleh +50 Koin Nutrisi Seimbang.' : null,
                    ],
                    'summary'        => $summary,
                    'detected_items' => $detectedItems,
                    'plate_analysis' => $plateAnalysis,
                ],
                'completed_at' => now()->toIso8601String(),
            ], now()->addHours(24));

            Log::info("ProcessFoodNutritionJob [{$this->jobId}]: Sukses diproses.");

        } catch (\Throwable $e) {
            Log::error("ProcessFoodNutritionJob [{$this->jobId}]: Exception - " . $e->getMessage());
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
        $error = $exception ? $exception->getMessage() : 'Gagal mengeksekusi job segmentasi makanan.';
        Log::error("ProcessFoodNutritionJob [{$this->jobId}] FAILED: {$error}");
        $this->markFailed($error);
        $this->cleanupFile();
    }

    private function markFailed(string $error): void
    {
        Cache::put("ai_job:{$this->jobId}", [
            'status'    => 'failed',
            'progress'  => 0,
            'job_id'    => $this->jobId,
            'type'      => 'food_nutrition',
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
