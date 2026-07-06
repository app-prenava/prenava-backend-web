<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class GroqService
{
    private string $apiKey;
    private string $model;
    private int $timeout;

    public function __construct()
    {
        $this->apiKey  = config('services.groq.api_key', env('GROQ_API_KEY', ''));
        $this->model   = config('services.groq.model', env('GROQ_MODEL', 'llama-3.3-70b-versatile'));
        $this->timeout = (int) config('services.groq.timeout', env('GROQ_TIMEOUT', 30));
    }

    /**
     * Generate cooking guide for recommended foods.
     */
    public function getCookingGuide(array $foodNames): ?array
    {
        $foodList = implode(', ', $foodNames);

        $prompt = <<<PROMPT
Kamu adalah ahli gizi untuk ibu hamil. Berikan panduan memasak sehat menggunakan bahan-bahan berikut:

Bahan: {$foodList}

Berikan respons dalam format JSON berikut (tanpa markdown, hanya JSON murni):
{
  "cooking_guide": "Langkah-langkah memasak sehat...",
  "nutrition_tips": "Tips nutrisi untuk ibu hamil...",
  "meal_plan": "Saran menu harian..."
}

Gunakan bahasa Indonesia sederhana yang mudah dipahami ibu hamil.
Fokus pada cara memasak yang mempertahankan nutrisi.
Jangan gunakan MSG atau pengawet.
PROMPT;

        return $this->callGroq($prompt);
    }

    /**
     * Generate dynamic and personalized meal plan based on user health profile.
     */
    public function generateDynamicMealPlan(array $userProfile): ?array
    {
        $profileJson = json_encode($userProfile, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $prompt = <<<PROMPT
Kamu adalah ahli gizi prenatal (kehamilan) tingkat lanjut.
Berikut adalah profil kesehatan, kondisi, dan preferensi dari seorang ibu hamil:
{$profileJson}

Tugasmu:
1. JANGAN menebak angka kalori/protein! Gunakan angka PASTI dari 'kebutuhan_kalori_harian_tepat' dan 'kebutuhan_protein_harian_tepat' yang ada di profil untuk mengisi field "kebutuhan" di analisis gizi.
2. Analisis nutrisi apa saja yang kemungkinan besar Kurang (misal: jika Hb rendah berarti butuh Zat Besi).
3. Buatkan rekomendasi menu harian (Sarapan, Makan Siang, Makan Malam) yang sehat untuk ibu hamil, menggunakan bahan lokal Indonesia.
   - PENTING: JANGAN rekomendasikan makanan yang terlalu manis/tinggi gula atau kurang bergizi seperti Gudeg, kecuali user yang memintanya! Pilih makanan padat gizi (ikan, ayam bakar, telur, sayur bening).
4. Berikan saran personal yang suportif.

Berikan respons HANYA dalam format JSON dengan struktur persis seperti ini:
{
  "analisis_gizi": [
    {
      "nutrisi": "Kalori",
      "status": "Terpenuhi / Kurang / Berlebih",
      "kebutuhan": "...",
      "asupan": "...",
      "catatan": "..."
    }
  ],
  "rekomendasi_menu": {
    "sarapan": {
      "menu": ["...", "..."],
      "alasan": "..."
    },
    "makan_siang": {
      "menu": ["...", "..."],
      "alasan": "..."
    },
    "makan_malam": {
      "menu": ["...", "..."],
      "alasan": "..."
    }
  },
  "saran_ai": [
    "...",
    "..."
  ],
  "catatan": "Rekomendasi ini disusun berdasarkan hasil analisis kondisi ibu dan kebutuhan nutrisi saat ini..."
}

Pastikan format JSON valid agar bisa di-parsing oleh sistem.
PROMPT;

        return $this->callGroq($prompt);
    }

    /**
     * Call Groq API and parse the JSON response.
     */
    private function callGroq(string $prompt): ?array
    {
        if (empty($this->apiKey)) {
            Log::warning('GroqService: No API key configured');
            return null;
        }

        $url = "https://api.groq.com/openai/v1/chat/completions";

        try {
            $response = Http::timeout($this->timeout)
                ->retry(2, 500)
                ->withToken($this->apiKey)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                ])
                ->post($url, [
                    'model' => $this->model,
                    'messages' => [
                        ['role' => 'system', 'content' => 'Kamu adalah asisten AI yang pandai berbahasa Indonesia dan hanya membalas dengan format JSON valid.'],
                        ['role' => 'user', 'content' => $prompt]
                    ],
                    'temperature' => 0.5,
                    'response_format' => ['type' => 'json_object']
                ]);

            if ($response->failed()) {
                Log::error('GroqService: API error', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return null;
            }

            return $this->parseResponse($response->json());

        } catch (Exception $e) {
            Log::error('GroqService: Request failed', [
                'message' => $e->getMessage(),
            ]);
            return null;
        }
    }

    private function parseResponse(?array $response): ?array
    {
        if (empty($response)) {
            return null;
        }

        $text = $response['choices'][0]['message']['content'] ?? null;

        if (empty($text)) {
            return null;
        }

        // Strip markdown code fences if present
        $text = preg_replace('/^```json\s*/', '', $text);
        $text = preg_replace('/\s*```$/', '', $text);
        $text = trim($text);

        $parsed = json_decode($text, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::warning('GroqService: Failed to parse JSON response', [
                'raw_text' => substr($text, 0, 500),
            ]);
            return ['raw_text' => $text];
        }

        return is_array($parsed) ? $parsed : null;
    }
}
