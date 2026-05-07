<?php

namespace App\Console\Commands;

use App\Services\NutritionImportService;
use Illuminate\Console\Command;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

class SyncFoodDataset extends Command
{
    protected $signature = 'food:sync-dataset {--with-recipes : Sync Indonesian_Food_Recipes.csv to foods table} {--recipes-table : Import full Indonesian_Food_Recipes.csv to food_recipes table} {--receipt-dir : Import all dataset-*.csv in storage/app/dataset/receipt to food_recipes table} {--link-recipes : Link food_recipes rows to foods using name similarity score}';
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

        if ($this->option('recipes-table')) {
            $this->info('Importing full recipes CSV into food_recipes table ...');
            $tableStats = NutritionImportService::importRecipesToTable();
            $this->line('Recipe table import stats: ' . json_encode($tableStats));
        }

        if ($this->option('receipt-dir')) {
            $this->info('Importing receipt directory CSV files into food_recipes table ...');
            $dirStats = NutritionImportService::importReceiptDirectoryToTable();
            $this->line('Receipt directory import stats: ' . json_encode($dirStats));
        }

        if ($this->option('link-recipes')) {
            $this->info('Linking food_recipes to foods using similarity score ...');
            $linkStats = NutritionImportService::linkRecipesToFoods();
            $this->line('Recipe linking stats: ' . json_encode($linkStats));
        }

        $this->info('Food dataset sync completed.');
        return SymfonyCommand::SUCCESS;
    }
}
