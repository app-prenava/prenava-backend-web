<?php

namespace App\Services;

use App\Helpers\StuntingMapper;
use App\Models\StuntingPrediction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\ConnectionException;
use Exception;

class StuntingPredictionService
{
    private string $mlBaseUrl;
    private int $timeout;
    private int $connectTimeout;

    public function __construct()
    {
        $this->mlBaseUrl = rtrim(config('services.ml.stunting', 'http://127.0.0.1:8000'), '/');
        $this->timeout        = (int) config('services.ml.stunting_timeout', 90);
        $this->connectTimeout = (int) config('services.ml.stunting_connect_timeout', 10);
    }

    /**
     * Run full predict flow: map → call ML → store → return.
     */
    public function predict(array $humanInput, int $userId): StuntingPrediction
    {
        // 1) Map humanized input → ML payload
        $mlPayload = StuntingMapper::toMlPayload($humanInput);

        // 2) Call FastAPI ML service with latency measurement
        $startTime = microtime(true);

        try {
            Log::info('StuntingML: Preparing prediction', ['user_id' => $userId]);
            $mlResponse = $this->callMlService($mlPayload);
            $latencyMs  = (int) round((microtime(true) - $startTime) * 1000);

            Log::info('StuntingML: Received response', [
                'status' => 'success',
                'latency' => $latencyMs,
                'prediction' => $mlResponse['prediction'] ?? 'N/A'
            ]);

            // 3) Persist successful prediction inside a transaction
            return DB::transaction(function () use ($humanInput, $mlPayload, $mlResponse, $userId, $latencyMs) {
                return StuntingPrediction::create([
                    'user_id'         => $userId,
                    'input_data'      => $humanInput,
                    'ml_payload'      => $mlPayload,
                    'ml_response'     => $mlResponse,
                    'probability'     => $mlResponse['probability']     ?? null,
                    'prediction'      => $mlResponse['prediction']      ?? null,
                    'risk_label'      => $mlResponse['risk_label']      ?? null,
                    'explanation'     => $mlResponse['explanation']     ?? null,
                    'recommendations' => $mlResponse['recommendations'] ?? null,
                    'model_version'   => $mlResponse['model_version']   ?? null,
                    'latency_ms'      => $latencyMs,
                    'status'          => StuntingPrediction::STATUS_SUCCESS,
                ]);
            });

        } catch (ConnectionException $e) {
            $latencyMs = (int) round((microtime(true) - $startTime) * 1000);

            // Store failed prediction for audit trail
            $this->storeFailedPrediction($humanInput, $mlPayload, $userId, $latencyMs, StuntingPrediction::STATUS_TIMEOUT);

            Log::error('StuntingML: Timeout', [
                'url'        => "{$this->mlBaseUrl}/predict",
                'latency_ms' => $latencyMs,
                'message'    => $e->getMessage(),
            ]);

            throw new Exception(
                'Layanan prediksi stunting tidak merespons. Silakan coba lagi.',
                504,
                $e
            );

        } catch (Exception $e) {
            $latencyMs = (int) round((microtime(true) - $startTime) * 1000);

            $this->storeFailedPrediction($humanInput, $mlPayload, $userId, $latencyMs, StuntingPrediction::STATUS_FAILED);

            throw $e;
        }
    }

    /**
     * Call FastAPI /predict endpoint.
     *
     * @throws ConnectionException  On timeout
     * @throws Exception            On non-2xx response
     */
    private function callMlService(array $payload): array
    {
        $response = Http::timeout($this->timeout)
            ->connectTimeout($this->connectTimeout)
            ->withHeaders([
                'Accept'       => 'application/json',
                'Content-Type' => 'application/json',
            ])
            ->post("{$this->mlBaseUrl}/predict", $payload);

        if ($response->failed()) {
            Log::error('StuntingML: Upstream error', [
                'status' => $response->status(),
                'body'   => substr($response->body(), 0, 500),
            ]);

            throw new Exception(
                "ML service returned HTTP {$response->status()}.",
                $response->status() === 504 ? 504 : 502
            );
        }

        return $response->json();
    }

    /**
     * Store a failed prediction record for observability.
     */
    private function storeFailedPrediction(
        array $humanInput,
        array $mlPayload,
        int $userId,
        int $latencyMs,
        string $status
    ): void {
        try {
            StuntingPrediction::create([
                'user_id'    => $userId,
                'input_data' => $humanInput,
                'ml_payload' => $mlPayload,
                'latency_ms' => $latencyMs,
                'status'     => $status,
            ]);
        } catch (Exception $e) {
            Log::error('StuntingML: Failed to store error record', [
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get paginated prediction history for a user (successful only).
     */
    public function getHistory(int $userId, int $perPage = 10)
    {
        return StuntingPrediction::forUser($userId)
            ->successful()
            ->latestFirst()
            ->paginate($perPage);
    }

    /**
     * Get single prediction detail (ownership-scoped).
     */
    public function getDetail(int $userId, int $predictionId): ?StuntingPrediction
    {
        return StuntingPrediction::forUser($userId)
            ->where('id', $predictionId)
            ->first();
    }

    /**
     * Return humanized questionnaire metadata for mobile UI.
     */
    public static function getQuestions(): array
    {
        return [
            [
                'key'      => 'child_gender',
                'label'    => 'Jenis kelamin anak',
                'type'     => 'select',
                'required' => true,
                'options'  => [
                    ['value' => 'male',   'label' => 'Laki-laki'],
                    ['value' => 'female', 'label' => 'Perempuan'],
                ],
            ],
            [
                'key'      => 'mother_education',
                'label'    => 'Pendidikan terakhir ibu',
                'type'     => 'select',
                'required' => true,
                'options'  => [
                    ['value' => 'tidak_sekolah', 'label' => 'Tidak Sekolah'],
                    ['value' => 'sd',            'label' => 'SD'],
                    ['value' => 'smp',           'label' => 'SMP'],
                    ['value' => 'sma',           'label' => 'SMA'],
                    ['value' => 'diploma',       'label' => 'Diploma'],
                    ['value' => 'sarjana',       'label' => 'Sarjana'],
                ],
            ],
            [
                'key'      => 'mother_employment',
                'label'    => 'Status pekerjaan ibu',
                'type'     => 'select',
                'required' => true,
                'options'  => [
                    ['value' => 'working',     'label' => 'Bekerja'],
                    ['value' => 'not_working', 'label' => 'Tidak Bekerja'],
                ],
            ],
            [
                'key'      => 'mother_height_cm',
                'label'    => 'Berapa tinggi badan ibu? (cm)',
                'type'     => 'number',
                'required' => true,
                'min'      => 100,
                'max'      => 200,
                'unit'     => 'cm',
            ],
            [
                'key'      => 'mother_age_at_birth',
                'label'    => 'Usia ibu saat melahirkan (tahun)',
                'type'     => 'number',
                'required' => true,
                'min'      => 12,
                'max'      => 55,
                'unit'     => 'tahun',
            ],
            [
                'key'      => 'water_access',
                'label'    => 'Akses air bersih',
                'type'     => 'select',
                'required' => true,
                'options'  => [
                    ['value' => 'safe',   'label' => 'Aman / Layak'],
                    ['value' => 'unsafe', 'label' => 'Tidak Aman / Tidak Layak'],
                ],
            ],
            [
                'key'      => 'sanitation_access',
                'label'    => 'Akses sanitasi',
                'type'     => 'select',
                'required' => true,
                'options'  => [
                    ['value' => 'proper',   'label' => 'Layak'],
                    ['value' => 'improper', 'label' => 'Tidak Layak'],
                ],
            ],
            [
                'key'      => 'home_ownership',
                'label'    => 'Status kepemilikan rumah',
                'type'     => 'select',
                'required' => true,
                'options'  => [
                    ['value' => 'owned',  'label' => 'Milik Sendiri'],
                    ['value' => 'rented', 'label' => 'Sewa / Kontrak'],
                ],
            ],
            [
                'key'      => 'has_electricity',
                'label'    => 'Apakah rumah memiliki listrik?',
                'type'     => 'boolean',
                'required' => true,
            ],
            [
                'key'      => 'has_refrigerator',
                'label'    => 'Apakah rumah memiliki kulkas?',
                'type'     => 'boolean',
                'required' => true,
            ],
            [
                'key'      => 'has_tv',
                'label'    => 'Apakah rumah memiliki TV?',
                'type'     => 'boolean',
                'required' => true,
            ],
            [
                'key'      => 'delivery_insurance',
                'label'    => 'Apakah memiliki jaminan/asuransi persalinan?',
                'type'     => 'boolean',
                'required' => true,
            ],
            [
                'key'      => 'anc_place',
                'label'    => 'Tempat pemeriksaan kehamilan (ANC)',
                'type'     => 'select',
                'required' => true,
                'options'  => [
                    ['value' => 'clinic_midwife',    'label' => 'Klinik / Bidan'],
                    ['value' => 'hospital',          'label' => 'Rumah Sakit'],
                    ['value' => 'traditional_other', 'label' => 'Dukun / Lainnya'],
                    ['value' => 'unknown',           'label' => 'Tidak Diketahui'],
                ],
            ],
        ];
    }
}
