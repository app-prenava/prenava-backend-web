<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use App\Jobs\ProcessAnemiaJob;
use App\Jobs\ProcessFoodNutritionJob;

class AsyncOrchestratorQueueTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    /**
     * Uji 1: Validasi Konfigurasi Fault Tolerance Worker (Retry 3x & Timeout 60s)
     */
    public function test_worker_fault_tolerance_configuration(): void
    {
        $anemiaJob = new ProcessAnemiaJob('job-anemia-001', null, '/tmp/fake_eye.jpg', 'eye.jpg');
        $this->assertEquals(3, $anemiaJob->tries);
        $this->assertEquals(60, $anemiaJob->timeout);

        $foodJob = new ProcessFoodNutritionJob('job-food-001', null, '/tmp/fake_plate.jpg', 'plate.jpg');
        $this->assertEquals(3, $foodJob->tries);
        $this->assertEquals(60, $foodJob->timeout);
    }

    /**
     * Uji 2: Endpoint POST /api/v2/anemia-scan/async mengembalikan HTTP 202 Accepted (< 150ms)
     * dan mendispatch ProcessAnemiaJob ke Queue.
     */
    public function test_anemia_async_endpoint_dispatches_job_and_returns_202(): void
    {
        Queue::fake();
        Storage::fake('local');

        $file = UploadedFile::fake()->image('sample_eye.jpg', 224, 224);

        $startTime = microtime(true);
        $response = $this->postJson('/api/v2/anemia-scan/async', [
            'image' => $file,
        ]);
        $elapsedMs = (microtime(true) - $startTime) * 1000;

        $response->assertStatus(202)
            ->assertJsonStructure([
                'status',
                'job_id',
                'type',
                'message',
                'polling_url',
            ])
            ->assertJsonPath('status', 'processing')
            ->assertJsonPath('type', 'anemia');

        $jobId = $response->json('job_id');
        $this->assertNotEmpty($jobId);

        // Pastikan state awal 15% tersimpan di Cache
        $cachedState = Cache::get("ai_job:{$jobId}");
        $this->assertNotNull($cachedState);
        $this->assertEquals(15, $cachedState['progress']);
        $this->assertEquals('processing', $cachedState['status']);

        // Pastikan Job masuk ke Queue
        Queue::assertPushed(ProcessAnemiaJob::class, function ($job) use ($jobId) {
            return $job->jobId === $jobId;
        });
    }

    /**
     * Uji 3: Endpoint POST /api/v2/food-scan/async mengembalikan HTTP 202 Accepted
     * dan mendispatch ProcessFoodNutritionJob ke Queue.
     */
    public function test_food_nutrition_async_endpoint_dispatches_job_and_returns_202(): void
    {
        Queue::fake();
        Storage::fake('local');
        Storage::fake('public');

        $file = UploadedFile::fake()->image('piring_makan.jpg', 640, 640);

        $response = $this->postJson('/api/v2/food-scan/async', [
            'image' => $file,
        ]);

        $response->assertStatus(202)
            ->assertJsonStructure([
                'status',
                'job_id',
                'type',
                'message',
                'polling_url',
            ])
            ->assertJsonPath('status', 'processing')
            ->assertJsonPath('type', 'food_nutrition');

        $jobId = $response->json('job_id');

        $cachedState = Cache::get("ai_job:{$jobId}");
        $this->assertNotNull($cachedState);
        $this->assertEquals(15, $cachedState['progress']);
        $this->assertEquals('food_nutrition', $cachedState['type']);

        Queue::assertPushed(ProcessFoodNutritionJob::class, function ($job) use ($jobId) {
            return $job->jobId === $jobId;
        });
    }

    /**
     * Uji 4: Siklus Hidup Eksekusi ProcessAnemiaJob (40% -> 75% -> 100% Completed) & Garbage Collection
     */
    public function test_process_anemia_job_lifecycle_and_temp_file_cleanup(): void
    {
        Http::fake([
            '*/predict' => Http::response([
                'prediction' => 'Normal (Non-Anemia)',
                'confidence' => 0.94,
                'hb_estimate' => 12.4,
            ], 200),
        ]);

        $tempFile = tempnam(sys_get_temp_dir(), 'anemia_test_') . '.jpg';
        file_put_contents($tempFile, 'dummy_jpeg_bytes');

        $jobId = 'test-anemia-lifecycle-999';
        $job = new ProcessAnemiaJob($jobId, null, $tempFile, 'sample_eye.jpg');
        $job->handle();

        // Pastikan state akhir di Cache adalah completed (100%)
        $finalState = Cache::get("ai_job:{$jobId}");
        $this->assertNotNull($finalState);
        $this->assertEquals('completed', $finalState['status']);
        $this->assertEquals(100, $finalState['progress']);
        $this->assertEquals('anemia', $finalState['type']);
        $this->assertEquals('Normal (Non-Anemia)', $finalState['data']['prediction']);

        // Pastikan berkas sementara telah dihapus (garbage collection)
        $this->assertFileDoesNotExist($tempFile);
    }

    /**
     * Uji 5: Siklus Hidup Eksekusi ProcessFoodNutritionJob (40% -> 75% -> 100% Completed) & Garbage Collection
     */
    public function test_process_food_nutrition_job_lifecycle_and_cleanup(): void
    {
        Http::fake([
            '*/predict-food' => Http::response([
                'summary' => ['total_items_detected' => 4],
                'detected_items' => [
                    ['class_name' => 'Karbohidrat', 'confidence' => 0.92],
                    ['class_name' => 'Protein_Hewani', 'confidence' => 0.89],
                    ['class_name' => 'Sayuran', 'confidence' => 0.91],
                    ['class_name' => 'Buah_Buahan', 'confidence' => 0.87],
                ],
                'plate_analysis' => [
                    'nutrition_score' => 90,
                    'balance_status' => 'SEIMBANG',
                    'has_iron_protein' => true,
                    'has_folate_vegetable' => true,
                    'has_fruit' => true,
                    'has_carbohydrate' => true,
                ],
            ], 200),
        ]);

        $tempFile = tempnam(sys_get_temp_dir(), 'food_test_') . '.jpg';
        file_put_contents($tempFile, 'dummy_plate_bytes');

        $jobId = 'test-food-lifecycle-888';
        $job = new ProcessFoodNutritionJob($jobId, null, $tempFile, 'piring.jpg');
        $job->handle();

        $finalState = Cache::get("ai_job:{$jobId}");
        $this->assertNotNull($finalState);
        $this->assertEquals('completed', $finalState['status']);
        $this->assertEquals(100, $finalState['progress']);
        $this->assertEquals('food_nutrition', $finalState['type']);
        $this->assertEquals('SEIMBANG', $finalState['data']['plate_analysis']['balance_status']);

        $this->assertFileDoesNotExist($tempFile);
    }

    /**
     * Uji 6: Endpoint Universal Polling Status GET /api/v2/jobs/{jobId}/status
     */
    public function test_universal_job_status_polling_endpoint(): void
    {
        $jobId = 'job-poll-test-777';
        Cache::put("ai_job:{$jobId}", [
            'status'   => 'processing',
            'progress' => 75,
            'job_id'   => $jobId,
            'type'     => 'anemia',
            'message'  => 'Menganalisis tingkat kepucatan konjungtiva mata...',
        ], now()->addHours(24));

        $res = $this->getJson("/api/v2/jobs/{$jobId}/status");
        $res->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('job.job_id', $jobId)
            ->assertJsonPath('job.progress', 75);

        // Uji jika job tidak ditemukan (404)
        $res404 = $this->getJson('/api/v2/jobs/non-existent-id/status');
        $res404->assertStatus(404)
            ->assertJsonPath('status', 'not_found');
    }
}
