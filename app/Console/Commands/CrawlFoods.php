<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CrawlFoods extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crawl:foods';

    protected $description = 'Crawl food data from internet using Firecrawl and store locally';

    public function handle(\App\Services\FirecrawlService $firecrawl)
    {
        $this->info('Starting food crawl...');
        
        // List of queries to get diverse categories of food
        $queries = [
            'Resep masakan rumahan sehat dan murah untuk ibu hamil lengkap dengan estimasi harga',
            'Resep masakan sumber protein hewani murah untuk ibu hamil lengkap dengan harga',
            'Resep sayuran sehat dan murah untuk ibu hamil',
            'Menu camilan sehat untuk ibu hamil dengan harga murah'
        ];
        
        $totalSaved = 0;
        
        foreach ($queries as $query) {
            $this->info("Crawling for: {$query}");
            $results = $firecrawl->searchAndExtractFood($query);
            
            $this->info('Found ' . count($results) . ' items.');
            
            foreach ($results as $item) {
                // Upsert based on name
                \App\Models\ScrapedFood::updateOrCreate(
                    ['name' => $item['name']],
                    [
                        'description' => $item['description'] ?? null,
                        'image_url' => $item['image_url'] ?? null,
                        'price_estimate' => isset($item['price_estimate']) ? (float)$item['price_estimate'] : null,
                        'category' => $item['category'] ?? null,
                        'source_url' => $item['source_url'] ?? null,
                    ]
                );
                $totalSaved++;
            }
            
            // Sleep to avoid hitting rate limits
            sleep(2);
        }
        
        $this->info("Crawl completed! Saved/updated {$totalSaved} items in scraped_foods.");
    }
}
