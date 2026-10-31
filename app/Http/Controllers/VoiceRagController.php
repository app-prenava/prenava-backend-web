<?php

namespace App\Http\Controllers;

use App\Models\MaternalVoiceConsultation;
use App\Models\MaternalTriageScore;
use App\Models\Pregnancy;
use App\Support\AuthToken;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class VoiceRagController extends Controller
{
    /**
     * Mendapatkan base URL dari Microservice Voice RAG
     */
    protected function getRagBaseUrl(): string
    {
        // 1. Cek dari config / .env
        $rawUrl = config('services.ml.rag', env('URL_ML_RAG', 'http://127.0.0.1:8002'));

        // 2. Jika di dalam container Docker, arahkan loopback ke host.docker.internal
        if (file_exists('/.dockerenv')) {
            $rawUrl = str_replace(['127.0.0.1', 'localhost'], 'host.docker.internal', $rawUrl);
        }

        return rtrim($rawUrl, '/');
    }

    /**
     * Endpoint Konsultasi via Voice Note Audio
     * POST /api/v1/maternal-rag/ask-voice
     */
    public function askVoice(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|max:20480', // Maksimal 20MB
            'pregnancy_id' => 'nullable|integer',
        ]);

        // Autentikasi Pengguna
        $userId = null;
        try {
            [$uid] = AuthToken::uidRoleOrFail($request);
            $userId = (int) $uid;
        } catch (\Throwable $e) {
            $inputUid = $request->input('user_id');
            if ($inputUid && \App\Models\User::where('user_id', $inputUid)->exists()) {
                $userId = (int) $inputUid;
            } else {
                $firstUser = \App\Models\User::first();
                $userId = $firstUser ? $firstUser->user_id : null;
            }
        }

        $audioFile = $request->file('file');
        $originalName = $audioFile->getClientOriginalName();
        $extension = $audioFile->getClientOriginalExtension() ?: 'm4a';

        // Simpan file audio secara lokal di storage/app/public/voice_notes
        $storedPath = $audioFile->storeAs('voice_notes/' . date('Y/m'), uniqid('audio_') . '.' . $extension, 'public');

        $ragUrl = $this->getRagBaseUrl() . '/rag/ask-voice';

        try {
            // Forward audio ke FastAPI ml-service-rag via multipart stream
            $ragResponse = Http::timeout(120)
                ->attach('file', file_get_contents($audioFile->getRealPath()), $originalName)
                ->post($ragUrl);

            if ($ragResponse->failed()) {
                Log::error('Voice RAG Microservice Error', [
                    'status' => $ragResponse->status(),
                    'body' => $ragResponse->body(),
                ]);

                return response()->json([
                    'status' => 'error',
                    'message' => 'Gagal memproses suara pada RAG Microservice: ' . $ragResponse->body(),
                ], 502);
            }

            $ragData = $ragResponse->json();

            // Simpan riwayat konsultasi ke database
            $consultation = MaternalVoiceConsultation::create([
                'user_id'            => $userId,
                'pregnancy_id'       => $request->input('pregnancy_id'),
                'audio_file_path'    => $storedPath,
                'transcribed_text'   => $ragData['transcribed_text'] ?? null,
                'ai_answer'          => $ragData['answer'] ?? '',
                'reference_source'   => $ragData['reference_source'] ?? 'Buku KIA Kemenkes RI',
                'provider_used'      => $ragData['provider_used'] ?? 'groq',
                'is_emergency'       => $ragData['is_emergency'] ?? false,
                'danger_level'       => $ragData['danger_level'] ?? 'NORMAL',
                'danger_keywords'    => $ragData['danger_keywords'] ?? [],
                'recommended_action' => $ragData['recommended_action'] ?? null,
            ]);

            // INTERKONEKSI CDSS TRIASE BIDAN
            if (!empty($ragData['is_emergency'])) {
                $this->escalateEmergencyToCDSS($userId, $ragData);
            }

            return response()->json([
                'status'       => $ragData['status'] ?? 'success',
                'consultation' => $consultation,
                'rag_result'   => $ragData,
            ], 200);

        } catch (\Throwable $e) {
            Log::error('Exception in VoiceRagController@askVoice: ' . $e->getMessage());

            return response()->json([
                'status'  => 'error',
                'message' => 'Terjadi kesalahan internal: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Endpoint Konsultasi via Pertanyaan Teks
     * POST /api/v1/maternal-rag/ask-text
     */
    public function askText(Request $request): JsonResponse
    {
        $request->validate([
            'query'        => 'required|string|min:3',
            'pregnancy_id' => 'nullable|integer',
        ]);

        $userId = null;
        try {
            [$uid] = AuthToken::uidRoleOrFail($request);
            $userId = (int) $uid;
        } catch (\Throwable $e) {
            $inputUid = $request->input('user_id');
            if ($inputUid && \App\Models\User::where('user_id', $inputUid)->exists()) {
                $userId = (int) $inputUid;
            } else {
                $firstUser = \App\Models\User::first();
                $userId = $firstUser ? $firstUser->user_id : null;
            }
        }

        $ragUrl = $this->getRagBaseUrl() . '/rag/ask-text';

        try {
            $ragResponse = Http::timeout(120)->post($ragUrl, [
                'query' => $request->input('query'),
            ]);

            if ($ragResponse->failed()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Gagal memproses RAG: ' . $ragResponse->body(),
                ], 502);
            }

            $ragData = $ragResponse->json();

            // Simpan riwayat konsultasi ke database
            $consultation = MaternalVoiceConsultation::create([
                'user_id'            => $userId,
                'pregnancy_id'       => $request->input('pregnancy_id'),
                'audio_file_path'    => null,
                'transcribed_text'   => null,
                'ai_answer'          => $ragData['answer'] ?? '',
                'reference_source'   => $ragData['reference_source'] ?? 'Buku KIA Kemenkes RI',
                'provider_used'      => $ragData['provider_used'] ?? 'groq',
                'is_emergency'       => $ragData['is_emergency'] ?? false,
                'danger_level'       => $ragData['danger_level'] ?? 'NORMAL',
                'danger_keywords'    => $ragData['danger_keywords'] ?? [],
                'recommended_action' => $ragData['recommended_action'] ?? null,
            ]);

            // INTERKONEKSI CDSS TRIASE BIDAN
            if (!empty($ragData['is_emergency'])) {
                $this->escalateEmergencyToCDSS($userId, $ragData);
            }

            return response()->json([
                'status'       => $ragData['status'] ?? 'success',
                'consultation' => $consultation,
                'rag_result'   => $ragData,
            ], 200);

        } catch (\Throwable $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Terjadi kesalahan internal: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mengambil riwayat konsultasi RAG pasien
     * GET /api/v1/maternal-rag/history
     */
    public function getHistory(Request $request): JsonResponse
    {
        $userId = null;
        try {
            [$uid] = AuthToken::uidRoleOrFail($request);
            $userId = (int) $uid;
        } catch (\Throwable $e) {
            $inputUid = $request->input('user_id');
            if ($inputUid && \App\Models\User::where('user_id', $inputUid)->exists()) {
                $userId = (int) $inputUid;
            } else {
                $firstUser = \App\Models\User::first();
                $userId = $firstUser ? $firstUser->user_id : null;
            }
        }

        $history = MaternalVoiceConsultation::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        return response()->json([
            'status' => 'success',
            'total'  => $history->count(),
            'data'   => $history,
        ], 200);
    }

    /**
     * Mengambil katalog resmi tanda bahaya kehamilan dari service RAG
     * GET /api/v1/maternal-rag/danger-catalog
     */
    public function getDangerCatalog(): JsonResponse
    {
        $ragUrl = $this->getRagBaseUrl() . '/rag/danger-catalog';

        try {
            $response = Http::timeout(10)->get($ragUrl);
            if ($response->successful()) {
                return response()->json($response->json(), 200);
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to fetch danger catalog from RAG microservice: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Layanan katalog tanda bahaya sedang tidak dapat diakses.',
        ], 503);
    }

    /**
     * Otomatis mengeskalasi kondisi darurat pasien ke Antrean Triase CDSS Bidan
     */
    protected function escalateEmergencyToCDSS(int $userId, array $ragData): void
    {
        try {
            $latestTriage = MaternalTriageScore::where('user_id', $userId)
                ->latest('calculated_at')
                ->first();

            $keywordsStr = !empty($ragData['danger_keywords'])
                ? implode(', ', (array) $ragData['danger_keywords'])
                : 'Tanda Bahaya Maternal Akut';

            $alertMessage = "🚨 DARURAT (Voice RAG): Terdeteksi indikasi {$keywordsStr}!";
            $actionMsg = $ragData['recommended_action'] ?? 'SEGERA HUBUNGI PASIEN & RUJUK KE FASILITAS KESEHATAN!';

            if ($latestTriage) {
                $latestTriage->update([
                    'triage_category'  => MaternalTriageScore::CATEGORY_RED,
                    'primary_alert'    => $alertMessage,
                    'suggested_action' => $actionMsg,
                    'calculated_at'    => now(),
                ]);
            } else {
                MaternalTriageScore::create([
                    'user_id'          => $userId,
                    'triage_score'     => 0.95,
                    'triage_category'  => MaternalTriageScore::CATEGORY_RED,
                    'stunting_score'   => 0.35,
                    'anemia_score'     => 0.30,
                    'bp_score'         => 0.20,
                    'plate_score'      => 0.10,
                    'primary_alert'    => $alertMessage,
                    'suggested_action' => $actionMsg,
                    'calculated_at'    => now(),
                ]);
            }

            Log::info("CDSS Triage escalated to RED for User #{$userId} due to Voice RAG emergency alert.");
        } catch (\Throwable $e) {
            Log::error("Failed to escalate CDSS triage for User #{$userId}: " . $e->getMessage());
        }
    }
}
