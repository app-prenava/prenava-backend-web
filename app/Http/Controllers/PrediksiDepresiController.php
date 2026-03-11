<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PrediksiDepresi;
use App\Models\ActivityLog;
use App\Services\ActivityLogService;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PrediksiDepresiController extends Controller
{

    // public function index()
    // {
    // return response()->json(PrediksiDepresi::all());
    // }

    public function index()
    {
        $user = Auth::user(); // Ambil user dari JWT

        // Ambil data hanya milik user tersebut
        $prediksidepresi = PrediksiDepresi::where('user_id', $user->id)->get();

        // Return direct array seperti ::all()
        return response()->json($prediksidepresi);
    }

        public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'umur' => 'required|integer|min:0|max:120',
            'merasa_sedih' => 'required|in:Tidak,Kadang-kadang,Ya',
            'mudah_tersinggung' => 'required|in:Tidak,Kadang-kadang,Ya',
            'masalah_tidur' => 'required|in:Tidak,Dua hari dalam seminggu/lebih,Ya',
            'masalah_fokus' => 'required|in:Tidak,Ya,Sering',
            'pola_makan' => 'required|in:Tidak sama sekali,Kadang-kadang,Ya',
            'merasa_bersalah' => 'required|in:Tidak,Mungkin,Ya',
            'suicide_attempt' => 'required|in:Tidak,Ya,Tidak ingin menjawab',
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => "error",
                "errors" => $validator->errors()
            ], 422);
        }

        try {
            $user = $request->user(); // user dari token
            if (!$user) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            // Data yang dikirim ke Flask (tanpa user_id)
            $dataKuisioner = [
                'umur' => $this->categorizeUmur($request->umur),
                'merasa_sedih' => $this->convertToNumber('merasa_sedih', $request->merasa_sedih),
                'mudah_tersinggung' => $this->convertToNumber('mudah_tersinggung', $request->mudah_tersinggung),
                'masalah_tidur' => $this->convertToNumber('masalah_tidur', $request->masalah_tidur),
                'masalah_fokus' => $this->convertToNumber('masalah_fokus', $request->masalah_fokus),
                'pola_makan' => $this->convertToNumber('pola_makan', $request->pola_makan),
                'merasa_bersalah' => $this->convertToNumber('merasa_bersalah', $request->merasa_bersalah),
                'suicide_attempt' => $this->convertToNumber('suicide_attempt', $request->suicide_attempt),
            ];

            // Prediksi dari Flask
            $hasilPrediksi = $this->predictDepresi($dataKuisioner);

            // Simpan ke database dengan user_id
            $prediksi = PrediksiDepresi::create(array_merge(
                $dataKuisioner,
                ['hasil_prediksi' => $hasilPrediksi, 'user_id' => $user->id]
            ));

            // Log deteksi depresi
            ActivityLogService::logFromUser(
                ActivityLog::TYPE_DETEKSI_DEPRESI,
                $user,
                "User {$user->name} melakukan deteksi tingkat depresi.",
                ['hasil_prediksi' => $hasilPrediksi],
                $request
            );

            return response()->json([
                "status" => "success",
                "message" => "Prediksi berhasil disimpan",
                "data" => $prediksi
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                "status" => "error",
                "message" => "Terjadi kesalahan saat memproses prediksi",
                "error_detail" => $e->getMessage()
            ], 500);
        }
    }


    public function show($id)
    {
        $prediksi = PrediksiDepresi::find($id);

        if (!$prediksi) {
            return response()->json([
                "status" => "error",
                "message" => "Data tidak ditemukan"
            ], 404);
        }

        // Konversi angka kembali ke teks
        $rekapData = [
            'umur' => $this->convertToText('umur', $prediksi->umur),
            'merasa_sedih' => $this->convertToText('merasa_sedih', $prediksi->merasa_sedih),
            'mudah_tersinggung' => $this->convertToText('mudah_tersinggung', $prediksi->mudah_tersinggung),
            'masalah_tidur' => $this->convertToText('masalah_tidur', $prediksi->masalah_tidur),
            'masalah_fokus' => $this->convertToText('masalah_fokus', $prediksi->masalah_fokus),
            'pola_makan' => $this->convertToText('pola_makan', $prediksi->pola_makan),
            'merasa_bersalah' => $this->convertToText('merasa_bersalah', $prediksi->merasa_bersalah),
            'suicide_attempt' => $this->convertToText('suicide_attempt', $prediksi->suicide_attempt),
            'hasil_prediksi' => $prediksi->hasil_prediksi ? "Terindikasi Depresi" : "Tidak Terindikasi Depresi"
        ];

        return response()->json([
            "status" => "success",
            "data" => $rekapData
        ]);
    }

    public function deletebyID($id)
    {
        $prediksi = PrediksiDepresi::find($id);

        if (!$prediksi) {
            return response()->json([
                "status" => "error",
                "message" => "Data tidak ditemukan"
            ], 404);
        }

        $prediksi->delete();

        return response()->json([
            "status" => "success",
            "message" => "History prediksi berhasil dihapus"
        ]);
    }

    private function categorizeUmur($umur)
    {
        if ($umur <= 30) return 0;
        elseif ($umur >= 31 && $umur <= 35) return 1;
        elseif ($umur >= 36 && $umur <= 40) return 2;
        elseif ($umur >= 41 && $umur <= 45) return 3;
        elseif ($umur >= 46 && $umur <= 50) return 4;
        else return null; // umur di luar jangkauan
    }

    private function convertToNumber($field, $value)
    {
        $mapping = [
            'merasa_sedih' => ['Tidak' => 0, 'Ya' => 1, 'Kadang-kadang' => 2],
            'mudah_tersinggung' => ['Tidak' => 0, 'Ya' => 1, 'Kadang-kadang' => 2],
            'masalah_tidur' => ['Tidak' => 0, 'Ya' => 1, 'Dua hari dalam seminggu/lebih' => 2],
            'masalah_fokus' => ['Tidak' => 0, 'Ya' => 1, 'Sering' => 2],
            'pola_makan' => ['Tidak sama sekali' => 2, 'Ya' => 1, 'Kadang-kadang' => 0],
            'merasa_bersalah' => ['Tidak' => 0, 'Ya' => 1, 'Mungkin' => 2],
            'suicide_attempt' => ['Tidak' => 0, 'Ya' => 1, 'Tidak ingin menjawab' => 2],
        ];

        return $mapping[$field][$value] ?? null;
    }

    private function convertToText($field, $value)
    {
        $reverseMapping = [
            'umur' => [
                0 => '0-30',
                1 => '31-35',
                2 => '36-40',
                3 => '41-45',
                4 => '46-50'
            ],
            'merasa_sedih' => [0 => 'Tidak', 1 => 'Ya', 2 => 'Kadang-kadang'],
            'mudah_tersinggung' => [0 => 'Tidak', 1 => 'Ya', 2 => 'Kadang-kadang'],
            'masalah_tidur' => [0 => 'Tidak', 1 => 'Ya', 2 => 'Dua hari dalam seminggu/lebih'],
            'masalah_fokus' => [0 => 'Tidak', 1 => 'Ya', 2 => 'Sering'],
            'pola_makan' => [2=> 'Tidak sama sekali', 1 => 'Ya', 0 => 'Kadang-kadang'],
            'merasa_bersalah' => [0 => 'Tidak', 1 => 'Ya', 2 => 'Mungkin'],
            'suicide_attempt' => [0 => 'Tidak', 1 => 'Ya', 2 => 'Tidak ingin menjawab'],
        ];

        return $reverseMapping[$field][$value] ?? "Tidak diketahui";
    }


    private function predictDepresi($data)
    {
        $response = Http::post('http://127.0.0.1:5000/predict', [
            // $response = Http::post('http://127.0.0.1:5000/predict', [
            'features' => array_values($data)
        ]);

        if ($response->failed()) {
            $status = $response->status();
            $body = $response->body();

            throw new \Exception("Gagal memanggil Flask API. Status: $status. Respons: $body");
        }

        return $response->json()['prediction'] ?? null;
    }

}
