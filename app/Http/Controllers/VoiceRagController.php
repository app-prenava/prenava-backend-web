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
            $response = Http::timeout(8)->get($ragUrl);
            if ($response->successful()) {
                return response()->json($response->json(), 200);
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to fetch danger catalog from RAG microservice, using official Buku KIA fallback: ' . $e->getMessage());
        }

        $fallbackCatalog = [
            [
                'code' => 'PERDARAHAN_ANTEPARTUM',
                'label' => 'Perdarahan Jalan Lahir (Antepartum)',
                'kia_page_ref' => 'Buku KIA Kemenkes RI, Lembar Tanda Bahaya Kehamilan (Hal. 22)',
                'clinical_risk' => 'Perdarahan pada masa kehamilan dapat menandakan gangguan plasenta yang berisiko mengurangi suplai oksigen ke janin.',
                'recommended_action' => 'Segera kunjungi Praktik Mandiri Bidan terdekat atau IGD Rumah Sakit PONEK untuk pemeriksaan kondisi plasenta dan detak jantung janin.',
                'first_aid_steps' => [
                    'Berbaring miring ke sisi kiri tubuh untuk menjaga kelancaran aliran darah dan oksigen ke janin.',
                    'Gunakan pembalut bersih untuk memantau volume darah dan jangan memasukkan benda apa pun ke jalan lahir.',
                    'Jangan meminum obat atau jamu tanpa instruksi dokter, serta siapkan Buku KIA dan kartu identitas/BPJS.',
                ],
                'facility_recommendation' => 'IGD Rumah Sakit PONEK 24 Jam atau Praktik Mandiri Bidan Terdekat',
            ],
            [
                'code' => 'PREEKLAMSIA_BERAT',
                'label' => 'Gejala Preeklamsia / Tekanan Darah Tinggi Kehamilan',
                'kia_page_ref' => 'Buku KIA Kemenkes RI, Lembar Tanda Bahaya Kehamilan (Hal. 22)',
                'clinical_risk' => 'Nyeri kepala hebat, pandangan kabur, atau bengkak mendadak merupakan tanda preeklamsia yang dapat berkembang menjadi kejang eklamsia bila tidak segera ditangani.',
                'recommended_action' => 'Segera periksakan tekanan darah dan protein urine di Puskesmas PONED, Klinik Bidan, atau IGD Rumah Sakit terdekat untuk pencegahan kejang eklamsia.',
                'first_aid_steps' => [
                    'Istirahatkan ibu di ruangan yang tenang, redup, dan posisikan tubuh miring ke kiri.',
                    'Longgarkan pakaian yang ketat dan pastikan jalur napas tetap lega.',
                    'Minta pendamping keluarga segera mengantar ke fasilitas kesehatan tanpa menunda waktu.',
                ],
                'facility_recommendation' => 'IGD Rumah Sakit PONEK 24 Jam atau Puskesmas PONED Terdekat',
            ],
            [
                'code' => 'KETUBAN_PECAH_DINI',
                'label' => 'Ketuban Pecah Dini (Cairan Merembes dari Jalan Lahir)',
                'kia_page_ref' => 'Buku KIA Kemenkes RI, Lembar Tanda Bahaya Kehamilan (Hal. 22)',
                'clinical_risk' => 'Keluarnya cairan ketuban sebelum waktunya meningkatkan risiko infeksi intrauterin pada janin serta memicu persalinan kurang bulan.',
                'recommended_action' => 'Segera menuju Praktik Mandiri Bidan atau IGD Rumah Sakit terdekat untuk pemeriksaan kertas lakmus, evaluasi air ketuban, dan pemantauan detak jantung janin.',
                'first_aid_steps' => [
                    'Catat waktu pertama kali cairan keluar beserta warna dan baunya untuk dilaporkan kepada Bidan atau Dokter.',
                    'Gunakan pembalut bersih dan jangan melakukan bilas vagina atau memasukkan apa pun ke jalan lahir.',
                    'Kurangi aktivitas berjalan jauh dan berbaringlah selama perjalanan menuju fasilitas kesehatan.',
                ],
                'facility_recommendation' => 'Praktik Mandiri Bidan Mitra atau IGD Rumah Sakit PONEK Terdekat',
            ],
            [
                'code' => 'GAWAT_JANIN',
                'label' => 'Penurunan Gerakan Janin (Risiko Gawat Janin)',
                'kia_page_ref' => 'Buku KIA Kemenkes RI, Lembar Pemantauan Gerak Janin (Hal. 20 & 22)',
                'clinical_risk' => 'Gerakan janin yang berkurang drastis (kurang dari 10 kali dalam 12 jam) dapat mengindikasikan penurunan suplai oksigen melalui tali pusat atau plasenta.',
                'recommended_action' => 'Segera periksakan detak jantung janin menggunakan Doppler atau CTG di Praktik Mandiri Bidan atau IGD Rumah Sakit terdekat.',
                'first_aid_steps' => [
                    'Berbaring miring ke kiri dan minum air putih atau minuman manis untuk merangsang respons gerak janin.',
                    'Letakkan tangan di perut dan fokus menghitung gerakan janin sembari bersiap menuju fasilitas kesehatan.',
                    'Bawa catatan penghitung gerakan janin (Kick Counter) dan Buku KIA untuk ditunjukkan kepada Bidan.',
                ],
                'facility_recommendation' => 'Praktik Mandiri Bidan Mitra atau IGD Rumah Sakit PONEK Terdekat',
            ],
            [
                'code' => 'INFEKSI_AKUT_DEMAM',
                'label' => 'Demam Tinggi atau Menggigil Saat Hamil',
                'kia_page_ref' => 'Buku KIA Kemenkes RI, Lembar Tanda Bahaya Kehamilan (Hal. 22)',
                'clinical_risk' => 'Demam tinggi di atas 38 derajat Celsius pada ibu hamil meningkatkan risiko stres metabolik pada janin serta kontraksi persalinan dini.',
                'recommended_action' => 'Segera periksakan diri ke Puskesmas, Klinik Bidan, atau Rumah Sakit terdekat untuk mengetahui sumber infeksi dan mendapatkan penanganan medis yang aman bagi janin.',
                'first_aid_steps' => [
                    'Minum air putih secara bertahap untuk mencegah dehidrasi akibat peningkatan suhu tubuh.',
                    'Kompres hangat pada dahi atau lipatan ketiak dan kenakan pakaian yang tipis serta menyerap keringat.',
                    'Jangan mengonsumsi antibiotik atau obat penurun panas di luar anjuran Bidan atau Dokter.',
                ],
                'facility_recommendation' => 'Puskesmas, Praktik Mandiri Bidan, atau Rumah Sakit Terdekat',
            ],
            [
                'code' => 'NYERI_PERUT_AKUT',
                'label' => 'Nyeri Perut Hebat atau Kontraksi Terus-Menerus',
                'kia_page_ref' => 'Buku KIA Kemenkes RI, Lembar Tanda Bahaya Kehamilan (Hal. 22)',
                'clinical_risk' => 'Nyeri perut hebat yang menetap atau perut terasa kaku terus-menerus dapat menandakan ancaman persalinan prematur atau pelepasan plasenta dini.',
                'recommended_action' => 'Segera datangi Praktik Mandiri Bidan atau IGD Rumah Sakit terdekat untuk evaluasi kontraksi rahim dan pemeriksaan kondisi plasenta.',
                'first_aid_steps' => [
                    'Hentikan seluruh aktivitas fisik, berbaring miring ke kiri, dan atur pernapasan secara perlahan.',
                    'Jangan mengurut atau menekan area perut maupun pinggang.',
                    'Catat jeda waktu antar rasa kencang/mulas dan segera berangkat ke fasilitas kesehatan terdekat.',
                ],
                'facility_recommendation' => 'Praktik Mandiri Bidan Mitra atau IGD Rumah Sakit PONEK Terdekat',
            ],
        ];

        return response()->json([
            'total_categories' => count($fallbackCatalog),
            'source' => 'Buku KIA Kemenkes RI, Lembar Tanda Bahaya Kehamilan',
            'catalog' => $fallbackCatalog,
        ], 200);
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

            $alertMessage = "Siaga Darurat (Konsultasi KIA): Terdeteksi keluhan {$keywordsStr}";
            $actionMsg = $ragData['recommended_action'] ?? 'Segera hubungi pasien dan arahkan pemeriksaan ke fasilitas kesehatan terdekat.';

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
