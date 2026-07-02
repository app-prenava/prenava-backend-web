<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class FirecrawlService
{
    private string $apiKey;
    private string $baseUrl = 'https://api.firecrawl.dev/v1';

    public function __construct()
    {
        $this->apiKey = config('services.firecrawl.api_key', '');
    }

    /**
     * Search and extract food data from the internet using Firecrawl.
     * 
     * @param string $query The search query (e.g., "Resep masakan sehat ibu hamil dengan harga")
     * @return array
     */
    public function searchAndExtractFood(string $query): array
    {
        if (empty($this->apiKey)) {
            Log::warning('Firecrawl API key is missing.');
            return [];
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type'  => 'application/json',
            ])->post($this->baseUrl . '/search', [
                'query' => $query,
                'limit' => 10,
                'scrapeOptions' => [
                    'formats' => ['json'],
                    'jsonOptions' => [
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'foods' => [
                                    'type' => 'array',
                                    'items' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'name' => ['type' => 'string', 'description' => 'Nama makanan atau resep'],
                                            'description' => ['type' => 'string', 'description' => 'Deskripsi singkat atau manfaat untuk ibu hamil'],
                                            'image_url' => ['type' => 'string', 'description' => 'URL gambar makanan jika ada'],
                                            'price_estimate' => ['type' => 'number', 'description' => 'Estimasi harga dalam rupiah (angka saja)'],
                                            'category' => ['type' => 'string', 'description' => 'Kategori (misal: sayuran, protein hewani, buah, dll)'],
                                            'source_url' => ['type' => 'string', 'description' => 'URL sumber informasi'],
                                        ],
                                        'required' => ['name', 'description', 'price_estimate', 'category']
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                // Firecrawl returns an array of search results, some might contain the extracted json
                $allFoods = [];
                
                if (isset($data['data']) && is_array($data['data'])) {
                    foreach ($data['data'] as $result) {
                        if (isset($result['json']['foods']) && is_array($result['json']['foods'])) {
                            foreach ($result['json']['foods'] as $food) {
                                // Add source url if missing but present in the parent result
                                if (empty($food['source_url']) && isset($result['metadata']['sourceURL'])) {
                                    $food['source_url'] = $result['metadata']['sourceURL'];
                                }
                                $allFoods[] = $food;
                            }
                        }
                    }
                }
                
                return $allFoods;
            }

            Log::error('Firecrawl API error', ['status' => $response->status(), 'body' => $response->body()]);
            return [];
        } catch (Exception $e) {
            Log::error('Firecrawl Exception', ['message' => $e->getMessage()]);
            return [];
        }
    }
}
