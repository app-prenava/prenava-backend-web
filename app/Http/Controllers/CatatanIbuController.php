<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use App\Models\CatatanIbu;
use App\Models\Pregnancy;
use App\Models\PregnancyAssessment;
use App\Services\MaternalTriageService;
use App\Support\AuthToken;
use Illuminate\Support\Facades\DB;

class CatatanIbuController extends Controller
{
    /**
     * 🔹 Create catatan kunjungan baru (oleh ibu hamil atau bidan)
     */
    public function store(Request $request): JsonResponse
    {
        [$uid, $role] = AuthToken::assertRoleFresh($request, ['ibu_hamil', 'bidan']);

        $validator = Validator::make($request->all(), [
            'user_id' => ['nullable', 'integer'],
            'tanggal_kunjungan' => ['required', 'date'],
            'status_kunjungan' => ['nullable', 'in:sedang_berlangsung,selesai'],
            'q1_demam' => ['nullable', 'boolean'],
            'q2_pusing' => ['nullable', 'boolean'],
            'q3_sulit_tidur' => ['nullable', 'boolean'],
            'q4_risiko_tb' => ['nullable', 'boolean'],
            'q5_gerakan_bayi' => ['nullable', 'boolean'],
            'q6_nyeri_perut' => ['nullable', 'boolean'],
            'q7_cairan_jalan_lahir' => ['nullable', 'boolean'],
            'q8_sakit_kencing' => ['nullable', 'boolean'],
            'q9_diare' => ['nullable', 'boolean'],
            'hasil_kunjungan' => ['nullable', 'string'],
            'systolic_bp' => ['nullable', 'numeric', 'min:40', 'max:280'],
            'diastolic_bp' => ['nullable', 'numeric', 'min:30', 'max:180'],
            'weight_kg' => ['nullable', 'numeric', 'min:20', 'max:250'],
            'fundal_height_cm' => ['nullable', 'numeric', 'min:5', 'max:55'],
            'fetal_heart_rate_bpm' => ['nullable', 'numeric', 'min:50', 'max:240'],
            'hemoglobin_gdl' => ['nullable', 'numeric', 'min:3', 'max:22'],
            'therapy_notes' => ['nullable', 'string'],
            'next_visit_date' => ['nullable', 'date'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        // Jika role ibu_hamil, gunakan user_id sendiri
        // Jika role bidan, bisa kirim user_id target
        $userId = $uid;

        if ($role === 'bidan' && $request->filled('user_id')) {
            $userId = (int) $request->input('user_id');
        }

        $hasVitalsCols = false;
        try {
            $hasVitalsCols = Schema::hasColumn('catatanibu', 'systolic_bp');
        } catch (\Throwable $e) {
            $hasVitalsCols = false;
        }

        $hasilText = $request->hasil_kunjungan ?? '';

        // Fallback packing jika kolom migrasi belum ada di DB fisik
        if (!$hasVitalsCols) {
            $vitalsPayload = array_filter([
                'systolic_bp' => $request->systolic_bp,
                'diastolic_bp' => $request->diastolic_bp,
                'weight_kg' => $request->weight_kg,
                'fundal_height_cm' => $request->fundal_height_cm,
                'fetal_heart_rate_bpm' => $request->fetal_heart_rate_bpm,
                'hemoglobin_gdl' => $request->hemoglobin_gdl,
                'therapy_notes' => $request->therapy_notes,
                'next_visit_date' => $request->next_visit_date,
            ], fn($v) => $v !== null);

            if (!empty($vitalsPayload)) {
                $hasilText = '[ANC_VITALS:' . json_encode($vitalsPayload) . "]\n" . $hasilText;
            }
        }

        $createData = [
            'user_id' => $userId,
            'tanggal_kunjungan' => $request->tanggal_kunjungan,
            'status_kunjungan' => $request->status_kunjungan ?? 'sedang_berlangsung',
            'q1_demam' => $request->q1_demam,
            'q2_pusing' => $request->q2_pusing,
            'q3_sulit_tidur' => $request->q3_sulit_tidur,
            'q4_risiko_tb' => $request->q4_risiko_tb,
            'q5_gerakan_bayi' => $request->q5_gerakan_bayi,
            'q6_nyeri_perut' => $request->q6_nyeri_perut,
            'q7_cairan_jalan_lahir' => $request->q7_cairan_jalan_lahir,
            'q8_sakit_kencing' => $request->q8_sakit_kencing,
            'q9_diare' => $request->q9_diare,
            'hasil_kunjungan' => $hasilText,
        ];

        if ($hasVitalsCols) {
            $createData['systolic_bp'] = $request->systolic_bp;
            $createData['diastolic_bp'] = $request->diastolic_bp;
            $createData['weight_kg'] = $request->weight_kg;
            $createData['fundal_height_cm'] = $request->fundal_height_cm;
            $createData['fetal_heart_rate_bpm'] = $request->fetal_heart_rate_bpm;
            $createData['hemoglobin_gdl'] = $request->hemoglobin_gdl;
            $createData['therapy_notes'] = $request->therapy_notes;
            $createData['next_visit_date'] = $request->next_visit_date;
        }

        $catatan = CatatanIbu::create($createData);

        // CDSS Real-time Triage Hook
        $this->syncCdssTriage($userId, $request);

        $catatan->load(['user.userProfile']);

        return response()->json([
            'status' => 'success',
            'message' => 'Catatan kunjungan berhasil dibuat',
            'data' => $this->formatResponse($catatan)
        ], 201);
    }

    /**
     * 🔹 Get semua catatan untuk user yang sedang login (ibu hamil)
     * atau semua catatan (untuk bidan)
     */
    public function index(Request $request): JsonResponse
    {
        [$uid, $role] = AuthToken::assertRoleFresh($request, ['ibu_hamil', 'bidan']);

        if ($role === 'ibu_hamil') {
            // Ibu hamil hanya bisa lihat catatan sendiri
            $catatan = CatatanIbu::with(['user.userProfile'])
                ->where('user_id', $uid)
                ->orderBy('tanggal_kunjungan', 'desc')
                ->get();
        } else {
            // Bidan bisa lihat semua catatan (atau filter by user_id jika ada)
            $query = CatatanIbu::with(['user.userProfile']);
            if ($request->filled('user_id')) {
                $query->where('user_id', $request->input('user_id'));
            }
            $catatan = $query->orderBy('tanggal_kunjungan', 'desc')->get();
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Data catatan berhasil diambil',
            'data' => $catatan->map(function ($item) {
                return $this->formatResponse($item);
            })
        ]);
    }

    /**
     * 🔹 Get detail catatan by ID
     */
    public function show(Request $request, int $catatanId): JsonResponse
    {
        [$uid, $role] = AuthToken::assertRoleFresh($request, ['ibu_hamil', 'bidan']);

        $catatan = CatatanIbu::with(['user.userProfile'])->find($catatanId);

        if (!$catatan) {
            return response()->json([
                'status' => 'error',
                'message' => 'Catatan tidak ditemukan'
            ], 404);
        }

        // Jika role ibu_hamil, hanya bisa lihat catatan sendiri
        if ($role === 'ibu_hamil' && $catatan->user_id !== $uid) {
            return response()->json([
                'status' => 'error',
                'message' => 'Access denied'
            ], 403);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Detail catatan berhasil diambil',
            'data' => $this->formatResponse($catatan)
        ]);
    }

    /**
     * 🔹 Update catatan (bisa oleh ibu hamil atau bidan)
     */
    public function update(Request $request, int $catatanId): JsonResponse
    {
        [$uid, $role] = AuthToken::assertRoleFresh($request, ['ibu_hamil', 'bidan']);

        $catatan = CatatanIbu::find($catatanId);

        if (!$catatan) {
            return response()->json([
                'status' => 'error',
                'message' => 'Catatan tidak ditemukan'
            ], 404);
        }

        // Jika role ibu_hamil, hanya bisa update catatan sendiri
        if ($role === 'ibu_hamil' && $catatan->user_id !== $uid) {
            return response()->json([
                'status' => 'error',
                'message' => 'Access denied'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'tanggal_kunjungan' => ['sometimes', 'required', 'date'],
            'status_kunjungan' => ['sometimes', 'in:sedang_berlangsung,selesai'],
            'q1_demam' => ['nullable', 'boolean'],
            'q2_pusing' => ['nullable', 'boolean'],
            'q3_sulit_tidur' => ['nullable', 'boolean'],
            'q4_risiko_tb' => ['nullable', 'boolean'],
            'q5_gerakan_bayi' => ['nullable', 'boolean'],
            'q6_nyeri_perut' => ['nullable', 'boolean'],
            'q7_cairan_jalan_lahir' => ['nullable', 'boolean'],
            'q8_sakit_kencing' => ['nullable', 'boolean'],
            'q9_diare' => ['nullable', 'boolean'],
            'hasil_kunjungan' => ['nullable', 'string'],
            'systolic_bp' => ['nullable', 'numeric', 'min:40', 'max:280'],
            'diastolic_bp' => ['nullable', 'numeric', 'min:30', 'max:180'],
            'weight_kg' => ['nullable', 'numeric', 'min:20', 'max:250'],
            'fundal_height_cm' => ['nullable', 'numeric', 'min:5', 'max:55'],
            'fetal_heart_rate_bpm' => ['nullable', 'numeric', 'min:50', 'max:240'],
            'hemoglobin_gdl' => ['nullable', 'numeric', 'min:3', 'max:22'],
            'therapy_notes' => ['nullable', 'string'],
            'next_visit_date' => ['nullable', 'date'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        $hasVitalsCols = false;
        try {
            $hasVitalsCols = Schema::hasColumn('catatanibu', 'systolic_bp');
        } catch (\Throwable $e) {
            $hasVitalsCols = false;
        }

        // Update data
        if ($request->has('tanggal_kunjungan')) {
            $catatan->tanggal_kunjungan = $request->tanggal_kunjungan;
        }

        if ($request->has('status_kunjungan')) {
            $catatan->status_kunjungan = $request->status_kunjungan;
        }

        // Update jawaban pertanyaan jika ada
        if ($request->has('q1_demam')) $catatan->q1_demam = $request->q1_demam;
        if ($request->has('q2_pusing')) $catatan->q2_pusing = $request->q2_pusing;
        if ($request->has('q3_sulit_tidur')) $catatan->q3_sulit_tidur = $request->q3_sulit_tidur;
        if ($request->has('q4_risiko_tb')) $catatan->q4_risiko_tb = $request->q4_risiko_tb;
        if ($request->has('q5_gerakan_bayi')) $catatan->q5_gerakan_bayi = $request->q5_gerakan_bayi;
        if ($request->has('q6_nyeri_perut')) $catatan->q6_nyeri_perut = $request->q6_nyeri_perut;
        if ($request->has('q7_cairan_jalan_lahir')) $catatan->q7_cairan_jalan_lahir = $request->q7_cairan_jalan_lahir;
        if ($request->has('q8_sakit_kencing')) $catatan->q8_sakit_kencing = $request->q8_sakit_kencing;
        if ($request->has('q9_diare')) $catatan->q9_diare = $request->q9_diare;

        // Update vitals & hasil kunjungan
        if ($hasVitalsCols) {
            if ($request->has('systolic_bp')) $catatan->systolic_bp = $request->systolic_bp;
            if ($request->has('diastolic_bp')) $catatan->diastolic_bp = $request->diastolic_bp;
            if ($request->has('weight_kg')) $catatan->weight_kg = $request->weight_kg;
            if ($request->has('fundal_height_cm')) $catatan->fundal_height_cm = $request->fundal_height_cm;
            if ($request->has('fetal_heart_rate_bpm')) $catatan->fetal_heart_rate_bpm = $request->fetal_heart_rate_bpm;
            if ($request->has('hemoglobin_gdl')) $catatan->hemoglobin_gdl = $request->hemoglobin_gdl;
            if ($request->has('therapy_notes')) $catatan->therapy_notes = $request->therapy_notes;
            if ($request->has('next_visit_date')) $catatan->next_visit_date = $request->next_visit_date;
            if ($request->has('hasil_kunjungan')) $catatan->hasil_kunjungan = $request->hasil_kunjungan;
        } else {
            // Update fallback JSON
            $currentHasil = $catatan->hasil_kunjungan ?? '';
            $currentVitals = [];
            if (preg_match('/\[ANC_VITALS:(.*?)\]/s', $currentHasil, $matches)) {
                $currentVitals = json_decode($matches[1], true) ?: [];
                $currentHasil = trim(str_replace($matches[0], '', $currentHasil));
            }
            if ($request->has('systolic_bp')) $currentVitals['systolic_bp'] = $request->systolic_bp;
            if ($request->has('diastolic_bp')) $currentVitals['diastolic_bp'] = $request->diastolic_bp;
            if ($request->has('weight_kg')) $currentVitals['weight_kg'] = $request->weight_kg;
            if ($request->has('fundal_height_cm')) $currentVitals['fundal_height_cm'] = $request->fundal_height_cm;
            if ($request->has('fetal_heart_rate_bpm')) $currentVitals['fetal_heart_rate_bpm'] = $request->fetal_heart_rate_bpm;
            if ($request->has('hemoglobin_gdl')) $currentVitals['hemoglobin_gdl'] = $request->hemoglobin_gdl;
            if ($request->has('therapy_notes')) $currentVitals['therapy_notes'] = $request->therapy_notes;
            if ($request->has('next_visit_date')) $currentVitals['next_visit_date'] = $request->next_visit_date;

            $newHasil = $request->has('hasil_kunjungan') ? $request->hasil_kunjungan : $currentHasil;
            if (!empty($currentVitals)) {
                $catatan->hasil_kunjungan = '[ANC_VITALS:' . json_encode($currentVitals) . "]\n" . $newHasil;
            } else {
                $catatan->hasil_kunjungan = $newHasil;
            }
        }

        $catatan->save();

        // CDSS Real-time Triage Hook
        $this->syncCdssTriage($catatan->user_id, $request);

        $catatan->load(['user.userProfile']);

        return response()->json([
            'status' => 'success',
            'message' => 'Catatan berhasil diupdate',
            'data' => $this->formatResponse($catatan)
        ]);
    }

    /**
     * 🔹 Delete catatan
     */
    public function destroy(Request $request, int $catatanId): JsonResponse
    {
        [$uid, $role] = AuthToken::assertRoleFresh($request, ['ibu_hamil', 'bidan']);

        $catatan = CatatanIbu::find($catatanId);

        if (!$catatan) {
            return response()->json([
                'status' => 'error',
                'message' => 'Catatan tidak ditemukan'
            ], 404);
        }

        // Jika role ibu_hamil, hanya bisa delete catatan sendiri
        if ($role === 'ibu_hamil' && $catatan->user_id !== $uid) {
            return response()->json([
                'status' => 'error',
                'message' => 'Access denied'
            ], 403);
        }

        $catatan->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Catatan berhasil dihapus'
        ]);
    }

    /**
     * 🔹 Hook sinkronisasi CDSS maternal triage dari data ANC
     */
    private function syncCdssTriage(int $userId, Request $request): void
    {
        try {
            $pregnancy = Pregnancy::where('user_id', $userId)
                ->where('status', 'ongoing')
                ->latest()
                ->first()
                ?? Pregnancy::where('user_id', $userId)->latest()->first();

            if ($pregnancy && ($request->filled('systolic_bp') || $request->filled('diastolic_bp'))) {
                $isHypertensive = ($request->systolic_bp >= 140 || $request->diastolic_bp >= 90);
                $hasFever = (bool) $request->q1_demam;

                PregnancyAssessment::updateOrCreate(
                    ['pregnancy_id' => $pregnancy->pregnancy_id],
                    [
                        'hypertension' => $isHypertensive,
                        'is_fever' => $hasFever,
                        'assessment_date' => now()->toDateString(),
                    ]
                );
            }

            if (class_exists(MaternalTriageService::class)) {
                app(MaternalTriageService::class)->calculatePatientTriage($userId, $pregnancy?->pregnancy_id);
            }
        } catch (\Throwable $e) {
            Log::warning("CatatanIbu CDSS triage sync skipped: " . $e->getMessage());
        }
    }

    /**
     * 🔹 Format response untuk frontend
     */
    private function formatResponse($catatan): array
    {
        $hasil = $catatan->hasil_kunjungan ?? '';
        $vitals = [
            'systolic_bp' => $catatan->systolic_bp,
            'diastolic_bp' => $catatan->diastolic_bp,
            'weight_kg' => $catatan->weight_kg,
            'fundal_height_cm' => $catatan->fundal_height_cm,
            'fetal_heart_rate_bpm' => $catatan->fetal_heart_rate_bpm,
            'hemoglobin_gdl' => $catatan->hemoglobin_gdl,
            'therapy_notes' => $catatan->therapy_notes,
            'next_visit_date' => $catatan->next_visit_date ? $catatan->next_visit_date->format('Y-m-d') : null,
        ];

        // Fallback parsing from header if columns were null
        if (preg_match('/\[ANC_VITALS:(.*?)\]/s', $hasil, $matches)) {
            $extracted = json_decode($matches[1], true);
            if (is_array($extracted)) {
                foreach ($extracted as $k => $v) {
                    if (!isset($vitals[$k]) || $vitals[$k] === null) {
                        $vitals[$k] = $v;
                    }
                }
            }
            $hasil = trim(str_replace($matches[0], '', $hasil));
        }

        $user = $catatan->user;
        $userName = $user?->userProfile?->nama_lengkap ?? $user?->name ?? 'Pasien';

        return [
            'catatan_id' => $catatan->catatan_id,
            'user_id' => $catatan->user_id,
            'nama_ibu' => $userName,
            'user' => $user ? [
                'user_id' => $user->user_id,
                'name' => $userName,
                'email' => $user->email,
                'phone' => $user->userProfile?->no_hp ?? $user->phone ?? null,
            ] : null,
            'tanggal_kunjungan' => $catatan->tanggal_kunjungan ? $catatan->tanggal_kunjungan->format('Y-m-d') : null,
            'status_kunjungan' => $catatan->status_kunjungan,
            'pertanyaan' => [
                [
                    'id' => 'q1_demam',
                    'pertanyaan' => 'Demam lebih dari 2 hari',
                    'jawaban' => $catatan->q1_demam,
                ],
                [
                    'id' => 'q2_pusing',
                    'pertanyaan' => 'Pusing/sakit kepala berat',
                    'jawaban' => $catatan->q2_pusing,
                ],
                [
                    'id' => 'q3_sulit_tidur',
                    'pertanyaan' => 'Sulit tidur/cemas berlebih',
                    'jawaban' => $catatan->q3_sulit_tidur,
                ],
                [
                    'id' => 'q4_risiko_tb',
                    'pertanyaan' => 'Risiko TB batuk lebih dari 2 minggu atau kontak serumah dengan penderita TB',
                    'jawaban' => $catatan->q4_risiko_tb,
                ],
                [
                    'id' => 'q5_gerakan_bayi',
                    'pertanyaan' => 'Gerakan bayi Tidak ada atau Kurang dari 10x dalam 12 jam setelah minggu ke-24',
                    'jawaban' => $catatan->q5_gerakan_bayi,
                ],
                [
                    'id' => 'q6_nyeri_perut',
                    'pertanyaan' => 'Nyeri perut hebat',
                    'jawaban' => $catatan->q6_nyeri_perut,
                ],
                [
                    'id' => 'q7_cairan_jalan_lahir',
                    'pertanyaan' => 'Keluar cairan dari online / jalan lahir sangat banyak atau berbau',
                    'jawaban' => $catatan->q7_cairan_jalan_lahir,
                ],
                [
                    'id' => 'q8_sakit_kencing',
                    'pertanyaan' => 'Sakit saat kencing Atau keluar keputihan atau gatal di daerah kemaluan',
                    'jawaban' => $catatan->q8_sakit_kencing,
                ],
                [
                    'id' => 'q9_diare',
                    'pertanyaan' => 'Diare berulang',
                    'jawaban' => $catatan->q9_diare,
                ],
            ],
            'hasil_kunjungan' => $hasil,
            'systolic_bp' => $vitals['systolic_bp'],
            'diastolic_bp' => $vitals['diastolic_bp'],
            'weight_kg' => $vitals['weight_kg'],
            'fundal_height_cm' => $vitals['fundal_height_cm'],
            'fetal_heart_rate_bpm' => $vitals['fetal_heart_rate_bpm'],
            'hemoglobin_gdl' => $vitals['hemoglobin_gdl'],
            'therapy_notes' => $vitals['therapy_notes'],
            'next_visit_date' => $vitals['next_visit_date'],
            'created_at' => $catatan->created_at,
            'updated_at' => $catatan->updated_at,
        ];
    }
}

