<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use App\Models\ScrapedFood;

class ScrapedFoodSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $path = database_path('seeders/data/scraped_foods.json');
        
        if (!File::exists($path)) {
            $this->command->error("File scraped_foods.json not found!");
            return;
        }

        $json = File::get($path);
        $foods = json_decode($json, true);

        if (!$foods) {
            $this->command->error("Invalid JSON data in scraped_foods.json!");
            return;
        }

        $this->command->info('Seeding ' . count($foods) . ' scraped foods...');

        $count = 0;
        foreach ($foods as $food) {
            if (empty($food['name'])) continue;

            ScrapedFood::updateOrCreate(
                ['name' => $food['name']], // prevent duplicate
                [
                    'description' => $food['description'] ?? null,
                    'image_url' => $food['image_url'] ?? null,
                    'price_estimate' => $food['price_estimate'] ?? null,
                    'category' => $food['category'] ?? null,
                    'source_url' => $food['source_url'] ?? null,
                ]
            );
            $count++;
        }

        $this->command->info("Successfully seeded {$count} scraped foods!");
    }
}
