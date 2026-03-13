<?php

namespace App\Http\Controllers;

use App\Models\SkorEpds;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;

class SkorEpdsController extends Controller
{
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'prediksi_depresi_id' => 'required|exists:prediksi_depresi,id',
                'answers' => 'required|array|size:10',
                'answers.*' => 'integer|min:0|max:3',
            ]);

            $score = array_sum($validated['answers']);

            $result = SkorEpds::create([
                'prediksi_depresi_id' => $request->input('prediksi_depresi_id'),
                'answers' => $validated['answers'],
                'score' => $score,
            ]);


            return response()->json([
                'message' => 'EPDS berhasil disimpan.',
                'score' => $score,
                'data' => $result
            ], 201);
        } catch (ValidationException $e) {
            Log::warning('Validasi gagal di store EPDS: ' . json_encode($e->errors()));
            return response()->json([
                'error' => 'Validasi gagal',
                'details' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Gagal menyimpan data EPDS: ' . $e->getMessage());
            return response()->json([
                'error' => 'Terjadi kesalahan saat menyimpan data.',
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(), // bisa dihapus saat production
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $result = SkorEpds::findOrFail($id);
            return response()->json($result);
        } catch (ModelNotFoundException $e) {
            Log::info("Data dengan ID $id tidak ditemukan.");
            return response()->json([
                'error' => 'Data tidak ditemukan.',
                'message' => $e->getMessage(),
            ], 404);
        } catch (\Exception $e) {
            Log::error('Gagal mengambil data EPDS: ' . $e->getMessage());
            return response()->json([
                'error' => 'Terjadi kesalahan saat mengambil data.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function index()
    {
        try {
            $results = SkorEpds::latest()->get();
            return response()->json($results);
        } catch (\Exception $e) {
            Log::error('Gagal mengambil list data EPDS: ' . $e->getMessage());
            return response()->json([
                'error' => 'Terjadi kesalahan saat mengambil data.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
