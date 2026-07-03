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
        $this->apiKey = (string) config('services.firecrawl.api_key', '');
    }

    /**
     * Extract food data from a specific URL to save Firecrawl credits.
     * 
     * @param string $url The URL of the recipe/food article
     * @return array
     */
    public function extractFoodsFromUrl(string $url): array
    {
        if (empty($this->apiKey)) {
            Log::warning('Firecrawl API key is missing.');
            return [];
        }

        try {
            $allFoods = [];

            $extractResponse = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type'  => 'application/json',
            ])->post($this->baseUrl . '/scrape', [
                'url' => $url,
                'formats' => ['extract'],
                'extract' => [
                    'prompt' => 'Extract food recipes or food items. Include name, description, and price_estimate. Infer category (sayuran, protein hewani, buah, camilan).',
                    'schema' => [
                        'type' => 'object',
                        'properties' => [
                            'foods' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'name' => ['type' => 'string'],
                                        'description' => ['type' => 'string'],
                                        'image_url' => ['type' => 'string', 'nullable' => true],
                                        'price_estimate' => ['type' => 'number', 'nullable' => true],
                                        'category' => ['type' => 'string']
                                    ],
                                    'required' => ['name', 'description', 'category']
                                ]
                            ]
                        ]
                    ]
                ]
            ]);

            if ($extractResponse->successful()) {
                $extractData = $extractResponse->json();
                
                if (isset($extractData['data']['extract']['foods']) && is_array($extractData['data']['extract']['foods'])) {
                    foreach ($extractData['data']['extract']['foods'] as $food) {
                        $food['source_url'] = $url;
                        $allFoods[] = $food;
                    }
                }
            } else {
                Log::error('Firecrawl Scrape API error for URL ' . $url, ['status' => $extractResponse->status(), 'body' => $extractResponse->body()]);
            }
            
            return $allFoods;
        } catch (Exception $e) {
            Log::error('Firecrawl Exception', ['message' => $e->getMessage()]);
            return [];
        }
    }
}
