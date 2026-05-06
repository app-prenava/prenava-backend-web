<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class GeminiService
{
    private string $apiKey;
    private string $model;
    private int $timeout;

    public function __construct()
    {
        $this->apiKey  = config('services.gemini.api_key', '');
        $this->model   = config('services.gemini.model', 'gemini-2.0-flash');
        $this->timeout = (int) config('services.gemini.timeout', 30);
    }

    /**
     * Generate cooking guide for recommended foods.
     *
     * @param array $foodNames List of food names
     * @return array|null Returns null on failure (graceful degradation)
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

        return $this->callGemini($prompt);
    }

    /**
     * Generate meal plan based on risk level and SHAP factors.
     *
     * @param string $riskLabel 'high_risk' or 'low_risk'
     * @param array $factorLabels Human-readable factor labels
     * @param array $foodNames Available food names
     * @return array|null
     */
    public function getMealPlan(string $riskLabel, array $factorLabels, array $foodNames): ?array
    {
        $riskText = $riskLabel === 'high_risk' ? 'TINGGI' : 'RENDAH';
        $factors  = implode(', ', $factorLabels);
        $foods    = implode(', ', $foodNames);

        $prompt = <<<PROMPT
Kamu adalah ahli gizi prenatal. Buatkan rencana makan harian untuk ibu hamil dengan:

Tingkat Risiko Stunting: {$riskText}
Faktor risiko: {$factors}
Bahan tersedia: {$foods}

Berikan respons dalam format JSON berikut (tanpa markdown, hanya JSON murni):
{
  "cooking_guide": "Panduan memasak sehat untuk ibu hamil...",
  "nutrition_tips": "Tips nutrisi berdasarkan faktor risiko...",
  "meal_plan": "Rencana makan: Sarapan... Makan Siang... Makan Malam... Camilan..."
}

Gunakan bahasa Indonesia sederhana.
Fokus pada nutrisi yang membantu mencegah stunting.
Berikan saran yang realistis dan terjangkau.
PROMPT;

        return $this->callGemini($prompt);
    }

    /**
     * Call Gemini API and parse the JSON response.
     *
     * @return array|null Returns null on any failure
     */
    private function callGemini(string $prompt): ?array
    {
        if (empty($this->apiKey)) {
            Log::warning('GeminiService: No API key configured');
            return null;
        }

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent";

        try {
            $response = Http::timeout($this->timeout)
                ->retry(2, 500)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                ])
                ->post("{$url}?key={$this->apiKey}", [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $prompt],
                            ],
                        ],
                    ],
                    'generationConfig' => [
                        'temperature'     => 0.7,
                        'maxOutputTokens' => 1024,
                        'responseMimeType' => 'application/json',
                    ],
                ]);

            if ($response->failed()) {
                Log::error('GeminiService: API error', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return null;
            }

            return $this->parseResponse($response->json());

        } catch (Exception $e) {
            Log::error('GeminiService: Request failed', [
                'message' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Parse the Gemini API response into structured data.
     */
    private function parseResponse(?array $response): ?array
    {
        if (empty($response)) {
            return null;
        }

        $text = $response['candidates'][0]['content']['parts'][0]['text'] ?? null;

        if (empty($text)) {
            return null;
        }

        // Strip markdown code fences if present
        $text = preg_replace('/^```json\s*/', '', $text);
        $text = preg_replace('/\s*```$/', '', $text);
        $text = trim($text);

        $parsed = json_decode($text, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::warning('GeminiService: Failed to parse JSON response', [
                'raw_text' => substr($text, 0, 500),
            ]);

            // Fallback: return the raw text as a cooking guide
            return [
                'cooking_guide'  => $text,
                'nutrition_tips' => null,
                'meal_plan'      => null,
            ];
        }

        return [
            'cooking_guide'  => $parsed['cooking_guide'] ?? null,
            'nutrition_tips' => $parsed['nutrition_tips'] ?? null,
            'meal_plan'      => $parsed['meal_plan'] ?? null,
        ];
    }
}
