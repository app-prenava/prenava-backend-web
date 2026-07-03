<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$apiKey = config('services.firecrawl.api_key');
$response = Illuminate\Support\Facades\Http::withHeaders([
    'Authorization' => 'Bearer ' . $apiKey,
    'Content-Type'  => 'application/json',
])->post('https://api.firecrawl.dev/v1/scrape', [
    'url' => 'https://hallobumil.com/kehamilan/kreasi-resep-sayur-sehat-untuk-hidup-lebih-bergizi',
    'formats' => ['extract'],
    'extract' => [
        'prompt' => 'Extract all food recipes mentioned. Include name, description, price_estimate.',
        'schema' => [
            'type' => 'object',
            'properties' => [
                'foods' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => ['type' => 'string'],
                            'description' => ['type' => 'string']
                        ],
                        'required' => ['name', 'description']
                    ]
                ]
            ]
        ]
    ]
]);

print_r($response->json());
