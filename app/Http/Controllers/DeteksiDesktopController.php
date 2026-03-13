<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use App\Models\DeteksiPenyakit;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Models\ActivityLog;
use App\Services\ActivityLogService;

class DeteksiDesktopController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        // Ambil data DeteksiPenyakit hanya milik user tersebut
        $deteksiPenyakit = DeteksiPenyakit::all();
        // $deteksiPenyakit = DeteksiPenyakit::where('user_id', $user->id)->get();
        
        return view('deteksi.index', compact('deteksiPenyakit'));
    }
        
    public function create()
    {
        return view('deteksi.form');
    }
    
    public function show($id)
    {
        $deteksi = DeteksiPenyakit::findOrFail($id);
        
        // Periksa apakah deteksi ini milik user yang login
        // if (Auth::id() !== $deteksi->user_id) {
        //     return redirect()->route('deteksi.index')->with('error', 'Anda tidak memiliki akses ke data ini');
        // }
        
        return view('deteksi.result', compact('deteksi'));
    }
    
    public function indexlatest()
    {
        $deteksiPenyakit = DeteksiPenyakit::latest()->first();
        
        if (!$deteksiPenyakit) {
            return redirect()->route('deteksi.index')->with('info', 'Belum ada data deteksi');
        }
        
        return redirect()->route('deteksi.show', ['id' => $deteksiPenyakit->id]);
    }
    
    public function store(Request $request)
    {
        // Log request data untuk debugging
        Log::info('DeteksiPenyakit store request received', [
            'user_id' => Auth::id(),
            'request_data' => $request->except(['_token']),
            'url' => $request->url(),
            'method' => $request->method(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent()
        ]);

        try {
            // --- MODIFIED VALIDATION ---
            // Validasi input baru: tinggi_badan dan berat_badan
            // BMI akan dihitung otomatis dari tinggi dan berat badan
            $validated = $request->validate([
                'nama' => 'required|string|max:255',
                'pregnancies' => 'required|integer|min:0|max:20',
                'age' => 'required|integer|min:15|max:100',
                'tinggi_badan' => 'required|numeric|min:100|max:250', // Validasi tinggi badan (cm)
                'berat_badan' => 'required|numeric|min:30|max:200',  // Validasi berat badan (kg)
                'bs_mgdl' => 'required|numeric|min:50|max:500', // Input gula darah dalam mg/dL
                'skin_thickness' => 'nullable|numeric|min:0|max:100',
                'current_smoker' => 'nullable|integer|in:0,1',
                'cigs_per_day' => 'nullable|integer|min:0|max:100',
                'bp_meds' => 'nullable|integer|in:0,1',
                'systolic_bp' => 'required|numeric|min:80|max:250',
                'diastolic_bp' => 'required|numeric|min:50|max:150',
                'heart_rate' => 'nullable|numeric|min:40|max:200',
                'body_temp' => 'nullable|numeric|min:35|max:42',
            ], [
                'nama.required' => 'Nama lengkap wajib diisi',
                'pregnancies.required' => 'Jumlah kehamilan wajib diisi',
                'age.required' => 'Umur wajib diisi',
                'age.min' => 'Umur minimal 15 tahun',
                'tinggi_badan.required' => 'Tinggi badan wajib diisi.',
                'berat_badan.required' => 'Berat badan wajib diisi.',
                'bs_mgdl.required' => 'Gula darah wajib diisi',
                'bs_mgdl.min' => 'Nilai gula darah tidak valid',
                'systolic_bp.required' => 'Tekanan sistolik wajib diisi',
                'diastolic_bp.required' => 'Tekanan diastolik wajib diisi',
            ]);

            Log::info('Validation passed successfully', ['validated_data' => $validated]);

            $user = Auth::user();
            if (!$user) {
                Log::warning('User not authenticated during store operation');
                return redirect()->route('login')->with('error', 'Anda harus login terlebih dahulu');
            }

            // --- HITUNG BMI OTOMATIS ---
            $tinggi_m = $request->tinggi_badan / 100; // Konversi cm ke meter
            $bmi = $request->berat_badan / ($tinggi_m * $tinggi_m);
            $bmi = round($bmi, 2);

            // Hitung MAP (Mean Arterial Pressure)
            $systolic = (float)$request->systolic_bp;
            $diastolic = (float)$request->diastolic_bp;
            $map = ($systolic + (2 * $diastolic)) / 3;

            // --- KONVERSI GULA DARAH dari mg/dL ke mmol/L ---
            $bs_mgdl = (float)$request->bs_mgdl;
            $bs_mmol = $bs_mgdl / 18.0182; // Faktor konversi
            $bs_mmol = round($bs_mmol, 2);

            // Set default values untuk field opsional
            $skinThickness = $request->skin_thickness ?? 0;
            $heartRate = $request->heart_rate ?? 70;
            $bodyTemp = $request->body_temp ?? 36.6;
            $currentSmoker = $request->current_smoker ?? 0;
            $cigsPerDay = ($currentSmoker == 1) ? ($request->cigs_per_day ?? 0) : 0;
            $bpMeds = $request->bp_meds ?? 0;

            Log::info('Calculated values', [
                'bmi' => $bmi,
                'map' => $map,
                'bs_mgdl' => $bs_mgdl,
                'bs_mmol' => $bs_mmol
            ]);

            // Simpan ke database
            $deteksi = DeteksiPenyakit::create([
                'user_id' => $user->id,
                'bidan_id' => $user->id,
                'nama' => $request->nama,
                'pregnancies' => $request->pregnancies,
                'age' => $request->age,
                'tinggi_badan' => $request->tinggi_badan,
                'berat_badan' => $request->berat_badan,
                'bmi' => $bmi, // BMI hasil perhitungan otomatis
                'blood_pressure' => round($map, 2),
                'bs' => $bs_mgdl, // Simpan sebagai mmol/L
                'skin_thickness' => $skinThickness,
                'sex' => 0,
                'current_smoker' => $currentSmoker,
                'cigs_per_day' => $cigsPerDay,
                'bp_meds' => $bpMeds,
                'systolic_bp' => $request->systolic_bp,
                'diastolic_bp' => $request->diastolic_bp,
                'heart_rate' => $heartRate,
                'body_temp' => $bodyTemp,
            ]);

            Log::info('DeteksiPenyakit created successfully', ['deteksi_id' => $deteksi->id]);

            // --- FORMAT DATA UNTUK API ML ---
            $requestData = [
                'diabetes' => [ // Menggunakan mg/dL untuk diabetes
                    'Pregnancies' => (int)$request->pregnancies,
                    'BS' => $bs_mgdl, // Kirim mg/dL untuk diabetes
                    'BloodPressure' => round($map, 2),
                    'SkinThickness' => (float)$skinThickness,
                    'BMI' => $bmi,
                    'Age' => (int)$request->age
                ],
                'hypertension' => [ // Menggunakan mmol/L untuk hypertension
                    'sex' => 0,
                    'Age' => (int)$request->age,
                    'currentSmoker' => (int)$currentSmoker,
                    'cigsPerDay' => (int)$cigsPerDay,
                    'BPMeds' => (int)$bpMeds,
                    'diabetes' => (int)($bs_mgdl > 140 ? 1 : 0),
                    'SystolicBP' => (float)$request->systolic_bp,
                    'DiastolicBP' => (float)$request->diastolic_bp,
                    'BMI' => $bmi,
                    'Heartrate' => (float)$heartRate,
                    'BS' => $bs_mgdl // Kirim mmol/L untuk hypertension
                ],
                'maternal_health' => [ // Menggunakan mmol/L untuk maternal health
                    'Age' => (int)$request->age,
                    'SystolicBP' => (float)$request->systolic_bp,
                    'DiastolicBP' => (float)$request->diastolic_bp,
                    'BS' => $bs_mmol, // Kirim mmol/L untuk maternal health
                    'BodyTemp' => (float)$bodyTemp,
                    'HeartRate' => (float)$heartRate
                ]
            ];

            Log::info('Sending request to ML API:', [
                'user_id' => $user->id,
                'deteksi_id' => $deteksi->id,
                'api_url' => 'https://sehatiml-production.up.railway.app/predictdeteksi',
                'request_data' => $requestData
            ]);

            $response = Http::timeout(30)->post('https://sehatiml-production.up.railway.app/predictdeteksi', $requestData);

            Log::info('ML API Response received', [
                'status' => $response->status(),
                'headers' => $response->headers(),
                'body' => $response->body(),
                'successful' => $response->successful()
            ]);

            if ($response->successful()) {
                $prediction = $response->json();
                
                if (isset($prediction['diabetes_prediction']) && isset($prediction['hypertension_prediction']) && isset($prediction['maternal_health_prediction'])) {
                    $deteksi->update([
                        'diabetes_prediction' => $prediction['diabetes_prediction'],
                        'hypertension_prediction' => $prediction['hypertension_prediction'],
                        'maternal_health_prediction' => $prediction['maternal_health_prediction'],
                    ]);

                    // Log deteksi anemia/risiko penyakit (Desktop)
                    ActivityLogService::logFromUser(
                        ActivityLog::TYPE_DETEKSI_ANEMIA,
                        $user,
                        "User {$user->name} melakukan deteksi risiko penyakit maternal via Desktop.",
                        ['predictions' => $prediction],
                        $request
                    );

                    Log::info('Predictions updated successfully', [
                        'deteksi_id' => $deteksi->id,
                        'predictions' => $prediction
                    ]);

                    return redirect()->route('deteksi.show', $deteksi->deteksi_id )
                        ->with('success', 'Data berhasil disimpan dan prediksi telah diterima.');
                } else {
                    Log::error('Unexpected response format from ML API', [
                        'response' => $prediction,
                        'expected_keys' => ['diabetes_prediction', 'hypertension_prediction', 'maternal_health_prediction']
                    ]);
                    return redirect()->route('deteksi.show', $deteksi->id)
                        ->with('warning', 'Data disimpan tetapi format respon prediksi tidak sesuai. Silakan hubungi administrator.');
                }
            } else {
                Log::error('HTTP Error from ML API', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'headers' => $response->headers()
                ]);
                return redirect()->route('deteksi.show', $deteksi->id)
                    ->with('warning', 'Data disimpan tetapi layanan prediksi tidak dapat diakses. Status: ' . $response->status());
            }

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('Validation failed', [
                'errors' => $e->errors(),
                'input' => $request->except(['_token'])
            ]);
            return redirect()->back()
                ->withErrors($e->validator)
                ->withInput()
                ->with('error', 'Mohon periksa kembali data yang dimasukkan');
        } catch (\Exception $e) {
            Log::error('Unexpected error in DeteksiPenyakit store', [
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'user_id' => Auth::id(),
                'request_data' => $request->except(['_token']),
                'trace' => $e->getTraceAsString()
            ]);
            return redirect()->back()
                ->with('error', 'Error: ' . $e->getMessage()) // tampilkan error detail
                ->withInput();
        }
    }
    
    public function deleteAll()
    {
        try {
            $userId = Auth::id();
            $deletedCount = DeteksiPenyakit::where('user_id', $userId)->count();
            
            Log::info('DeleteAll operation initiated', [
                'user_id' => $userId,
                'records_to_delete' => $deletedCount
            ]);
            
            DeteksiPenyakit::where('user_id', $userId)->delete();
            
            Log::info('DeleteAll operation completed successfully', [
                'user_id' => $userId,
                'deleted_count' => $deletedCount
            ]);
            
            return redirect()->route('deteksi.index')
                ->with('success', "Berhasil menghapus {$deletedCount} data deteksi");
                
        } catch (\Exception $e) {
            Log::error('Error in deleteAll operation', [
                'user_id' => Auth::id(),
                'error_message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return redirect()->route('deteksi.index')
                ->with('error', 'Terjadi kesalahan saat menghapus data. Silakan coba lagi.');
        }
    }

    public function destroy($id)
    {
        try {
            $deteksi = DeteksiPenyakit::find($id);
            
            if (!$deteksi) {
                Log::warning('Attempt to delete non-existent DeteksiPenyakit', [
                    'id' => $id,
                    'user_id' => Auth::id()
                ]);
                return redirect()->route('deteksi.index')->with('error', 'Data tidak ditemukan');
            }
            
            // if (Auth::id() !== $deteksi->user_id) {
            //     Log::warning('Unauthorized delete attempt', [
            //         'deteksi_id' => $id,
            //         'deteksi_user_id' => $deteksi->user_id,
            //         'current_user_id' => Auth::id()
            //     ]);
            //     return redirect()->route('deteksi.index')->with('error', 'Anda tidak memiliki akses untuk menghapus data ini');
            // }
            
            $deteksi->delete();
            
            Log::info('DeteksiPenyakit deleted successfully', [
                'deteksi_id' => $id,
                'user_id' => Auth::id()
            ]);
            
            return redirect()->route('deteksi.index')->with('success', 'Data berhasil dihapus');
            
        } catch (\Exception $e) {
            Log::error('Error in destroy operation', [
                'deteksi_id' => $id,
                'user_id' => Auth::id(),
                'error_message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return redirect()->route('deteksi.index')
                ->with('error', 'Terjadi kesalahan saat menghapus data. Silakan coba lagi.');
        }
    }
}