<?php

namespace App\Console\Commands;

use App\Services\NutritionImportService;
use Illuminate\Console\Command;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

class SyncFoodDataset extends Command
{
    protected $signature = 'food:sync-dataset {--with-recipes : Sync Indonesian_Food_Recipes.csv after nutrition import}';
    protected $description = 'Import nutrition dataset and optionally sync recipe dataset';

    public function handle(): int
    {
        $this->info('Importing nutrition.csv ...');
        $nutritionStats = NutritionImportService::importFromCsv();
        $this->line('Nutrition import stats: ' . json_encode($nutritionStats));

        if ($this->option('with-recipes')) {
            $this->info('Syncing Indonesian_Food_Recipes.csv ...');
            $recipeStats = NutritionImportService::syncRecipesFromCsv();
            $this->line('Recipe sync stats: ' . json_encode($recipeStats));
        }

        $this->info('Food dataset sync completed.');
        return SymfonyCommand::SUCCESS;
    }
}
