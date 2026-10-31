<?php

namespace Database\Seeders;

use App\Services\NutritionImportService;
use Illuminate\Database\Seeder;

class FoodSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->warn('FoodSeeder is deprecated: The static foods table was removed in migration drop_unused_food_and_meal_plan_tables.');
        $this->command->info('Meal planning has been refactored to use dynamic AI generation (Gemini/Groq API).');
    }
}
