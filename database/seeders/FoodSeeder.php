<?php

namespace Database\Seeders;

use App\Services\NutritionImportService;
use Illuminate\Database\Seeder;

class FoodSeeder extends Seeder
{
    public function run(): void
    {
        $stats = NutritionImportService::importFromCsv();

        $this->command->info("Foods imported: {$stats['imported']}");
        $this->command->info("Foods skipped (duplicates/zero): {$stats['skipped']}");

        if ($stats['errors'] > 0) {
            $this->command->warn("Errors: {$stats['errors']}");
        }
    }
}
