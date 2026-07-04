<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Models\StuntingPrediction;
use App\Models\Food;

class StuntingPredictionController extends Controller
{
    private $mlApiUrl;

    public function __construct()
    {
        $this->mlApiUrl = env('ML_API_URL', 'http://127.0.0.1:5000/predict');
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
                'unit' => 'cm'
            ],
            [
                'key' => 'mother_weight',
                'label' => 'Berat Badan Ibu (kg)',
                'type' => 'number',
                'unit' => 'kg'
            ],
            [
                'key' => 'maternal_hemoglobin',
                'label' => 'Hemoglobin Ibu (g/dL)',
                'type' => 'number',
                'unit' => 'g/dL'
            ],
            [
                'key' => 'mother_age',
                'label' => 'Usia Ibu',
                'type' => 'number',
                'unit' => 'tahun'
            ],
            [
                'key' => 'mother_education',
                'label' => 'Pendidikan Ibu (Tahun)',
                'type' => 'number',
                'unit' => 'tahun'
            ],
            [
                'key' => 'anc_visits_total',
                'label' => 'Total Kunjungan ANC',
                'type' => 'number',
                'unit' => 'kali'
            ],
            [
                'key' => 'iron_pills',
                'label' => 'Konsumsi Pil Besi',
                'type' => 'select',
                'options' => [
                    ['value' => '1', 'label' => 'Ya'],
                    ['value' => '0', 'label' => 'Tidak']
                ]
            ],
            [
                'key' => 'pregnancy_sickness',
                'label' => 'Sakit Selama Kehamilan',
                'type' => 'select',
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
     * Endpoint untuk daftar rekomendasi makanan dari DB Food (scraped)
     * GET /api/stunting/foods
     */
    public function getFoods(Request $request)
    {
        $limit = $request->query('per_page', 200); // limit to 200 for payload size
        $foods = Food::limit($limit)->get()->map(function ($food) {
            return [
                'id' => (string) $food->id,
                'name' => $food->name,
                'calories' => (float) $food->calories,
                'protein' => (float) $food->protein,
                'imageUrl' => $food->image_url,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $foods
        ]);
    }

    /**
     * Endpoint API ML Stunting
     * POST /api/stunting/predict
     */
    public function predict(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'mother_height' => 'required|numeric|min:100|max:250',
            'mother_weight' => 'required|numeric|min:30|max:200',
            'maternal_hemoglobin' => 'required|numeric|min:5|max:20',
            'mother_age' => 'required|numeric|min:12|max:60',
            'mother_education' => 'required|numeric|min:0|max:25',
            'anc_visits_total' => 'required|numeric|min:0|max:20',
            'iron_pills' => 'required|numeric|in:0,1',
            'pregnancy_sickness' => 'required|numeric|in:0,1',
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

            // MASKING: Isikan nilai default untuk data anak karena fokus pada ibu hamil
            $payload['child_age_years'] = 0;
            $payload['birth_weight'] = 3.0; // Normal birth weight
            $payload['child_gender'] = 1; // Default
            $payload['child_height'] = 50.0; // Normal birth height

            $startTime = microtime(true);
            $response = Http::timeout(10)->post($this->mlApiUrl, $payload);
            $latencyMs = (int) round((microtime(true) - $startTime) * 1000);

            if ($response->successful()) {
                $result = $response->json();
                
                $riskLevel = $result['prediction_class'] ?? 0;
                $score = $result['stunting_risk'] ?? 0.0;
                $riskLabel = $riskLevel == 1 ? "Tinggi" : ($riskLevel == 2 ? "Sedang" : "Rendah");
                
                $recommendationText = "Kondisi kehamilan Anda sehat. Pertahankan pola makan bergizi dan rutin periksa kehamilan (ANC).";
                if ($riskLevel == 1) {
                    $recommendationText = "Risiko stunting tinggi. Segera jadwalkan konsultasi dengan dokter kandungan Anda dan perhatikan asupan nutrisi.";
                } elseif ($riskLevel == 2) {
                    $recommendationText = "Risiko stunting sedang. Tingkatkan asupan makanan kaya protein hewani dan zat besi.";
                }

                $predictionRecord = null;
                if (Auth::check()) {
                    $predictionRecord = StuntingPrediction::create([
                        'user_id' => Auth::id(),
                        'input_data' => $payload,
                        'ml_payload' => $payload,
                        'ml_response' => $result,
                        'probability' => $score,
                        'prediction' => $riskLevel,
                        'risk_label' => $riskLabel,
                        'recommendations' => [$recommendationText],
                        'latency_ms' => $latencyMs,
                        'status' => StuntingPrediction::STATUS_SUCCESS,
                    ]);
                }

                $outputData = [
                    'score' => (float) $score,
                    'riskLevel' => (int) $riskLevel,
                    'riskLabel' => $riskLabel,
                    'recommendation' => $recommendationText,
                    'predictionId' => $predictionRecord ? $predictionRecord->id : 0,
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
                'message' => 'Terjadi kesalahan internal server saat memproses prediksi.'
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

        $recommendationText = !empty($prediction->recommendations) 
            ? $prediction->recommendations[0] 
            : "Tidak ada rekomendasi.";

        $outputData = [
            'score' => (float) $prediction->probability,
            'riskLevel' => (int) $prediction->prediction,
            'riskLabel' => $prediction->risk_label,
            'recommendation' => $recommendationText,
            'predictionId' => $prediction->id,
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
