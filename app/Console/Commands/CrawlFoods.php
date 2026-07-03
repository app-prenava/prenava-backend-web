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
        $this->info('Starting food crawl with direct URLs...');
        
        // List of specific URLs to scrape (saves credits compared to searching)
        // 15 link terfokus pada bahan pokok dari e-commerce/grocery web yang pasti ada gambarnya
        $urls = [
            // Sayurbox (Paling konsisten ada gambar & harga)
            'https://www.sayurbox.com/category/sayuran',
            'https://www.sayurbox.com/category/buah',
            'https://www.sayurbox.com/category/daging-ayam',
            'https://www.sayurbox.com/category/seafood',
            'https://www.sayurbox.com/category/protein-telur',
            
            // Tokopedia (Pencarian spesifik bahan makanan segar)
            'https://www.tokopedia.com/find/sayur-segar-sayuran',
            'https://www.tokopedia.com/find/daging-sapi-segar',
            'https://www.tokopedia.com/find/daging-ayam-segar',
            'https://www.tokopedia.com/find/ikan-segar',
            'https://www.tokopedia.com/find/telur-ayam',

            // Shopee (Pencarian spesifik bahan pokok segar)
            'https://shopee.co.id/search?keyword=daging%20sapi%20segar',
            'https://shopee.co.id/search?keyword=daging%20ayam%20segar',
            'https://shopee.co.id/search?keyword=sayuran%20segar',
            'https://shopee.co.id/search?keyword=telur%20ayam%20negeri',
            'https://shopee.co.id/search?keyword=tahu%20tempe%20segar'
        ];
        
        $totalSaved = 0;
        $allScrapedData = [];
        
        foreach ($urls as $url) {
            $this->info("Scraping URL: {$url}");
            $results = $firecrawl->extractFoodsFromUrl($url);
            
            $this->info('Found ' . count($results) . ' items.');
            
            foreach ($results as $item) {
                // For testing locally without DB, save to array
                $allScrapedData[] = [
                    'name' => $item['name'],
                    'description' => $item['description'] ?? null,
                    'image_url' => $item['image_url'] ?? null,
                    'price_estimate' => isset($item['price_estimate']) ? (float)$item['price_estimate'] : null,
                    'category' => $item['category'] ?? null,
                    'source_url' => $item['source_url'] ?? null,
                ];
                $totalSaved++;
            }
            
            // Simpan bertahap ke file JSON
            \Illuminate\Support\Facades\File::put(
                storage_path('app/scraped_foods_test.json'),
                json_encode($allScrapedData, JSON_PRETTY_PRINT)
            );
            
            // Sleep to avoid hitting rate limits
            sleep(2);
        }
        
        $this->info("Crawl completed! Saved {$totalSaved} items to storage/app/scraped_foods_test.json.");
    }
}
