<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class StuntingPredictionController extends Controller
{
    /**
     * Endpoint API ML Stunting (Flask)
     * Pastikan sesuai dengan port Docker (5000)
     */
    private $mlApiUrl;

    public function __construct()
    {
        // Mengambil URL dari .env, default ke localhost:5000 jika tidak diset
        $this->mlApiUrl = env('ML_API_URL', 'http://127.0.0.1:5000/predict');
    }

    /**
     * Menerima request dari Frontend, meneruskan ke ML API,
     * dan mengembalikan hasil prediksi.
     */
    public function predict(Request $request)
    {
        // 1. Validasi Input dari Frontend
        $validator = Validator::make($request->all(), [
            'mother_height' => 'required|numeric|min:100|max:250',
            'mother_weight' => 'required|numeric|min:30|max:200',
            'maternal_hemoglobin' => 'required|numeric|min:5|max:20',
            'mother_age' => 'required|numeric|min:12|max:60',
            'mother_education' => 'required|numeric|min:0|max:25',
            'anc_visits_total' => 'required|numeric|min:0|max:20',
            'iron_pills' => 'required|integer|in:0,1',
            'pregnancy_sickness' => 'required|integer|in:0,1',
            'birth_weight' => 'required|numeric|min:0.5|max:6.0',
            'child_gender' => 'required|integer|in:0,1,3',
            'child_height' => 'required|numeric|min:10|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // 2. Format Data untuk dikirim ke ML API
            // Catatan: child_age_years diset 0 untuk kehamilan
            $payload = $validator->validated();
            $payload['child_age_years'] = 0;

            // 3. Kirim request ke Flask API
            $response = Http::timeout(10)->post($this->mlApiUrl, $payload);

            if ($response->successful()) {
                $result = $response->json();
                
                // 4. (Opsional) Simpan ke Database
                // Contoh: StuntingHistory::create(['user_id' => auth()->id(), 'result' => $result['stunting_risk']]);
                
                // 5. Tambahkan Rekomendasi berdasarkan tingkat risiko
                $recommendation = "";
                if ($result['prediction_class'] == 1) {
                    $recommendation = "Risiko stunting tinggi. Segera jadwalkan konsultasi dengan dokter kandungan Anda dan perhatikan asupan nutrisi.";
                } else {
                    $recommendation = "Kondisi kehamilan Anda sehat. Pertahankan pola makan bergizi dan rutin periksa kehamilan (ANC).";
                }

                $result['recommendation'] = $recommendation;

                return response()->json($result, 200);
            } else {
                Log::error('ML API Error: ' . $response->body());
                return response()->json([
                    'status' => 'error',
                    'message' => 'Gagal mendapatkan prediksi dari server ML.'
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('Stunting Prediction Exception: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan internal server saat memproses prediksi.'
            ], 500);
        }
    }
}
