<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Models\StuntingPrediction;


class StuntingPredictionController extends Controller
{
    private $mlApiUrl;

    public function __construct()
    {
        $rawUrl = config('services.ml.stunting_predict', env('ML_API_URL', 'http://127.0.0.1:5000/predict'));
        if (file_exists('/.dockerenv')) {
            $rawUrl = str_replace(['127.0.0.1', 'localhost'], 'host.docker.internal', $rawUrl);
        }
        $this->mlApiUrl = $rawUrl;
    }

    /**
     * Endpoint untuk mengambil format pertanyaan form skrining Stunting
     * GET /api/stunting/questions
     */
    public function getQuestions()
    {
        $questions = [
            [
                'key' => 'mother_height',
                'label' => 'Tinggi Ibu (cm)',
                'type' => 'number',
                'unit' => 'cm',
                'required' => true,
            ],
            [
                'key' => 'mother_weight',
                'label' => 'Berat Badan Ibu (kg)',
                'type' => 'number',
                'unit' => 'kg',
                'required' => true,
            ],
            [
                'key' => 'maternal_hemoglobin',
                'label' => 'Hemoglobin Ibu (g/dL)',
                'type' => 'number',
                'unit' => 'g/dL',
                'required' => true,
            ],
            [
                'key' => 'mother_age',
                'label' => 'Usia Ibu (Tahun)',
                'type' => 'number',
                'unit' => 'tahun',
                'required' => true,
            ],
            [
                'key' => 'mother_education',
                'label' => 'Pendidikan Ibu',
                'type' => 'select',
                'required' => true,
                'options' => [
                    ['value' => '0', 'label' => 'Tidak Pernah Sekolah (0 Tahun)'],
                    ['value' => '6', 'label' => 'SD / Sederajat (6 Tahun)'],
                    ['value' => '9', 'label' => 'SMP / Sederajat (9 Tahun)'],
                    ['value' => '12', 'label' => 'SMA / SMK / Sederajat (12 Tahun)'],
                    ['value' => '15', 'label' => 'Diploma / D3 (15 Tahun)'],
                    ['value' => '16', 'label' => 'Sarjana / S1 / D4 (16 Tahun)'],
                    ['value' => '18', 'label' => 'Magister / S2+ (18+ Tahun)'],
                ]
            ],
            [
                'key' => 'anc_visits_total',
                'label' => 'Total Kunjungan ANC',
                'type' => 'number',
                'unit' => 'kali',
                'required' => true,
            ],
            [
                'key' => 'iron_pills',
                'label' => 'Konsumsi Pil Besi',
                'type' => 'select',
                'required' => true,
                'options' => [
                    ['value' => '1', 'label' => 'Ya'],
                    ['value' => '0', 'label' => 'Tidak']
                ]
            ],
            [
                'key' => 'pregnancy_sickness',
                'label' => 'Sakit Selama Kehamilan',
                'type' => 'select',
                'required' => true,
                'options' => [
                    ['value' => '1', 'label' => 'Ya'],
                    ['value' => '0', 'label' => 'Tidak']
                ]
            ],
        ];

        return response()->json([
            'success' => true,
            'data' => $questions
        ]);
    }



    /**
     * Endpoint API ML Stunting
     * POST /api/stunting/predict
     */
    public function predict(Request $request)
    {
        $input = $request->all();

        // Normalisasi Fleksibel Pendidikan Ibu
        if (isset($input['mother_education'])) {
            $edu = strtolower(trim((string)$input['mother_education']));
            $eduMap = [
                'tidak_sekolah' => 0,
                'tidak sekolah' => 0,
                'sd' => 6,
                'smp' => 9,
                'sma' => 12,
                'smk' => 12,
                'diploma' => 15,
                'd3' => 15,
                'd4' => 16,
                'sarjana' => 16,
                's1' => 16,
                'magister' => 18,
                's2' => 18,
                'doktor' => 21,
                's3' => 21,
            ];
            if (isset($eduMap[$edu])) {
                $input['mother_education'] = $eduMap[$edu];
            } elseif (is_numeric($input['mother_education'])) {
                $input['mother_education'] = (float)$input['mother_education'];
            }
        }

        // Normalisasi Boolean / Pil Besi & Sakit Hamil
        if (isset($input['iron_pills'])) {
            $val = $input['iron_pills'];
            $input['iron_pills'] = ($val === true || $val === 1 || $val === '1' || $val === 'true' || strtolower((string)$val) === 'ya') ? 1 : 0;
        }
        if (isset($input['pregnancy_sickness'])) {
            $val = $input['pregnancy_sickness'];
            $input['pregnancy_sickness'] = ($val === true || $val === 1 || $val === '1' || $val === 'true' || strtolower((string)$val) === 'ya') ? 1 : 0;
        }

        $validator = Validator::make($input, [
            'mother_height' => 'required|numeric|min:100|max:250',
            'mother_weight' => 'required|numeric|min:30|max:200',
            'maternal_hemoglobin' => 'required|numeric|min:5|max:20',
            'mother_age' => 'required|numeric|min:12|max:60',
            'mother_education' => 'required|numeric|min:0|max:25',
            'anc_visits_total' => 'required|numeric|min:0|max:20',
            'iron_pills' => 'required|numeric|in:0,1',
            'pregnancy_sickness' => 'required|numeric|in:0,1',
        ], [
            'mother_height.required' => 'Tinggi ibu wajib diisi.',
            'mother_weight.required' => 'Berat badan ibu wajib diisi.',
            'maternal_hemoglobin.required' => 'Kadar hemoglobin ibu wajib diisi.',
            'mother_age.required' => 'Usia ibu wajib diisi.',
            'mother_education.required' => 'Pendidikan ibu wajib diisi.',
            'mother_education.numeric' => 'Format pendidikan ibu tidak valid.',
            'mother_education.min' => 'Pendidikan ibu minimal 0 tahun.',
            'mother_education.max' => 'Pendidikan ibu maksimal 25 tahun.',
            'anc_visits_total.required' => 'Total kunjungan ANC wajib diisi.',
            'iron_pills.required' => 'Status konsumsi pil besi wajib diisi.',
            'pregnancy_sickness.required' => 'Status sakit selama kehamilan wajib diisi.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $payload = $validator->validated();
            
            // Konversi boolean/string dari form ke integer
            $payload['iron_pills'] = (int) $payload['iron_pills'];
            $payload['pregnancy_sickness'] = (int) $payload['pregnancy_sickness'];

            // Kirim payload murni data maternal ke ML service Fakhira (tanpa data anak dummy)
            $startTime = microtime(true);
            $response = Http::timeout(10)->post($this->mlApiUrl, $payload);
            $latencyMs = (int) round((microtime(true) - $startTime) * 1000);

            if ($response->successful()) {
                $result = $response->json();
                
                $riskLevel = $result['prediction_class'] ?? 0;
                $score = $result['probability'] ?? 0.0;
                $riskLabel = $result['stunting_risk'] ?? ($riskLevel == 1 ? "Tinggi" : "Rendah");
                $explanation = $result['explanation'] ?? $result['message'] ?? '';
                $recommendations = $result['recommendations'] ?? [];
                $riskFactors = $result['risk_factors'] ?? [];
                
                // Susun teks rekomendasi holistik
                if (!empty($recommendations)) {
                    $recommendationText = implode(" ", $recommendations);
                } else if (!empty($explanation)) {
                    $recommendationText = $explanation;
                } else {
                    $recommendationText = $riskLevel == 1 
                        ? "Risiko stunting tinggi. Segera jadwalkan konsultasi dengan dokter kandungan Anda dan perhatikan asupan nutrisi."
                        : "Kondisi kehamilan Anda sehat. Pertahankan pola makan bergizi dan rutin periksa kehamilan (ANC).";
                }

                // Format SHAP / Top Factors untuk kompatibilitas UI Flutter & Web
                $topFactors = [];
                foreach ($riskFactors as $rf) {
                    $topFactors[] = [
                        'feature' => $rf,
                        'impact'  => 'high',
                        'value'   => 1.0,
                        'message' => $rf,
                    ];
                }

                $structuredExplanation = [
                    'method'       => 'Clinical Risk Indicators (Kemenkes RI)',
                    'summary'      => $explanation,
                    'risk_factors' => $riskFactors,
                    'top_factors'  => $topFactors,
                ];

                $predictionRecord = null;
                if (Auth::check()) {
                    $predictionRecord = StuntingPrediction::create([
                        'user_id'         => Auth::id(),
                        'input_data'      => $payload,
                        'ml_payload'      => $payload,
                        'ml_response'     => $result,
                        'probability'     => $score,
                        'prediction'      => $riskLevel,
                        'risk_label'      => $riskLabel,
                        'explanation'     => $structuredExplanation,
                        'recommendations' => !empty($recommendations) ? $recommendations : [$recommendationText],
                        'model_version'   => 'v2.1.0-fakhira-maternal',
                        'latency_ms'      => $latencyMs,
                        'status'          => StuntingPrediction::STATUS_SUCCESS,
                    ]);

                    // Rekalkulasi Triase Klinis Maternal CDSS (Pilar ke-1 Risiko Stunting Maternal)
                    try {
                        $triageService = app(\App\Services\MaternalTriageService::class);
                        $triageService->calculatePatientTriage(Auth::id());
                    } catch (\Throwable $e) {
                        Log::warning("Gagal memperbarui Maternal Triage Score pasca prediksi stunting: " . $e->getMessage());
                    }
                }

                $outputData = [
                    'score'                 => (float) $score,
                    'probabilityPercentage' => round((float) $score * 100, 1),
                    'riskLevel'             => (int) $riskLevel,
                    'riskLabel'             => $riskLabel,
                    'recommendation'        => $recommendationText,
                    'recommendations'       => $recommendations,
                    'riskFactors'           => $riskFactors,
                    'explanation'           => $structuredExplanation,
                    'bmi'                   => $result['bmi'] ?? null,
                    'thresholdUsed'         => $result['threshold_used'] ?? null,
                    'predictionId'          => $predictionRecord ? $predictionRecord->id : 0,
                ];

                return response()->json([
                    'success' => true,
                    'data' => $outputData
                ], 200);
            } else {
                Log::error('ML API Error: ' . $response->body());
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal mendapatkan prediksi dari server ML.'
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('Stunting Prediction Exception: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan internal server saat memproses prediksi. Detail: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mengambil riwayat prediksi user
     * GET /api/stunting/history
     * 
     * OPSI 1: Bidan bisa lihat SEMUA data
     * User lain hanya lihat data sendiri
     */
    public function history()
    {
        $user = Auth::user();
        
        // OPSI 1: Bidan bisa lihat SEMUA data
        if ($user->role === 'bidan') {
            $query = StuntingPrediction::with('user:user_id,name,email')
                ->where('status', StuntingPrediction::STATUS_SUCCESS)
                ->latest();
        } else {
            // User lain hanya lihat data sendiri
            $userId = Auth::id();
            $query = StuntingPrediction::where('user_id', $userId)
                ->where('status', StuntingPrediction::STATUS_SUCCESS)
                ->latest();
        }
        
        $history = $query->get()
            ->map(function ($item) {
                $data = [
                    'id' => $item->id,
                    'date' => $item->created_at->toIso8601String(),
                    'status' => $item->status,
                    'riskLevel' => $item->prediction,
                    'score' => (float) $item->probability,
                ];
                
                // Tambahkan info user jika bidan mengakses
                if ($item->user) {
                    $data['user'] = [
                        'user_id' => $item->user->user_id,
                        'name' => $item->user->name,
                        'email' => $item->user->email,
                    ];
                }
                
                return $data;
            });

        return response()->json([
            'success' => true,
            'data' => $history
        ]);
    }

    /**
     * Mengambil detail riwayat tertentu
     * GET /api/stunting/history/{id}
     * 
     * OPSI 1: Bidan bisa lihat SEMUA data
     * User lain hanya lihat data sendiri
     */
    public function historyDetail($id)
    {
        $user = Auth::user();
        
        // OPSI 1: Bidan bisa lihat SEMUA data, user lain hanya data sendiri
        if ($user->role === 'bidan') {
            $prediction = StuntingPrediction::with('user:user_id,name,email')
                ->where('id', $id)
                ->first();
        } else {
            $userId = Auth::id();
            $prediction = StuntingPrediction::where('user_id', $userId)
                ->where('id', $id)
                ->first();
        }

        if (!$prediction) {
            return response()->json([
                'success' => false,
                'message' => 'Riwayat tidak ditemukan.'
            ], 404);
        }

        $recommendationText = "Tidak ada rekomendasi.";
        if (!empty($prediction->recommendations)) {
            $recommendationText = is_array($prediction->recommendations) 
                ? implode(" ", $prediction->recommendations) 
                : (string) $prediction->recommendations;
        }

        $outputData = [
            'score'                 => (float) $prediction->probability,
            'probabilityPercentage' => round((float) $prediction->probability * 100, 1),
            'riskLevel'             => (int) $prediction->prediction,
            'riskLabel'             => $prediction->risk_label,
            'recommendation'        => $recommendationText,
            'recommendations'       => $prediction->recommendations ?? [],
            'explanation'           => $prediction->explanation,
            'riskFactors'           => $prediction->explanation['risk_factors'] ?? [],
            'predictionId'          => $prediction->id,
            'inputData'             => $prediction->input_data,
            'aiMealPlan'            => $prediction->ai_meal_plan,
        ];
        
        // Tambahkan info user jika bidan mengakses
        if ($user->role === 'bidan' && $prediction->user) {
            $outputData['user'] = [
                'user_id' => $prediction->user->user_id,
                'name' => $prediction->user->name,
                'email' => $prediction->user->email,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $outputData
        ]);
    }
}
