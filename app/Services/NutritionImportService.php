<?php

namespace App\Services;

use App\Models\Food;
use App\Models\FoodRecipe;
use Illuminate\Support\Facades\Log;
use Exception;

class NutritionImportService
{
    /**
     * Import foods from CSV file into the database.
     *
     * CSV format: id,calories,proteins,fat,carbohydrate,name,image
     *
     * @return array{imported: int, skipped: int, errors: int}
     */
    public static function importFromCsv(string $path = null): array
    {
        $path = $path ?? storage_path('app/dataset/nutrition.csv');

        if (!file_exists($path)) {
            throw new Exception("CSV file not found: {$path}");
        }

        $handle = fopen($path, 'r');
        if (!$handle) {
            throw new Exception("Cannot open CSV file: {$path}");
        }

        // Read header row
        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            throw new Exception('CSV file is empty or has no header row.');
        }

        $header = array_map('trim', $header);

        $stats = ['imported' => 0, 'skipped' => 0, 'errors' => 0];
        $batch = [];
        $batchSize = 500;
        $existingNames = Food::pluck('name')->map(fn ($n) => mb_strtolower(trim($n)))->toArray();

        while (($row = fgetcsv($handle)) !== false) {
            try {
                $data = array_combine($header, array_map('trim', $row));

                if (empty($data['name'])) {
                    $stats['errors']++;
                    continue;
                }

                $normalizedName = mb_strtolower(trim($data['name']));

                // Skip duplicates
                if (in_array($normalizedName, $existingNames)) {
                    $stats['skipped']++;
                    continue;
                }

                $calories = self::parseFloat($data['calories'] ?? 0);
                $protein  = self::parseFloat($data['proteins'] ?? 0);
                $fat      = self::parseFloat($data['fat'] ?? 0);
                $carbs    = self::parseFloat($data['carbohydrate'] ?? 0);

                // Skip rows with all-zero nutrition
                if ($calories <= 0 && $protein <= 0 && $fat <= 0 && $carbs <= 0) {
                    $stats['skipped']++;
                    continue;
                }

                $batch[] = [
                    'name'          => trim($data['name']),
                    'category'      => null,
                    'protein'       => $protein,
                    'iron'          => null,
                    'calcium'       => null,
                    'vitamin_a'     => null,
                    'calories'      => $calories,
                    'carbohydrates' => $carbs,
                    'fat'           => $fat,
                    'image_url'     => self::sanitizeUrl($data['image'] ?? null),
                    'description'   => null,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ];

                $existingNames[] = $normalizedName;
                $stats['imported']++;

                if (count($batch) >= $batchSize) {
                    Food::insert($batch);
                    $batch = [];
                }

            } catch (Exception $e) {
                $stats['errors']++;
                Log::warning('NutritionImport: Row parse error', [
                    'message' => $e->getMessage(),
                ]);
            }
        }

        // Insert remaining batch
        if (!empty($batch)) {
            Food::insert($batch);
        }

        fclose($handle);

        return $stats;
    }

    /**
     * Sync recipe dataset into existing foods table.
     * Match strategy:
     * - exact normalized name
     * - fallback contains-match when exact is not found
     *
     * @return array{synced: int, skipped: int, unmatched: int, errors: int}
     */
    public static function syncRecipesFromCsv(string $path = null): array
    {
        $path = $path ?? storage_path('app/dataset/Indonesian_Food_Recipes.csv');

        if (!file_exists($path)) {
            throw new Exception("CSV file not found: {$path}");
        }

        $handle = fopen($path, 'r');
        if (!$handle) {
            throw new Exception("Cannot open CSV file: {$path}");
        }

        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            throw new Exception('CSV file is empty or has no header row.');
        }

        $header = array_map('trim', $header);
        $stats = ['synced' => 0, 'skipped' => 0, 'unmatched' => 0, 'errors' => 0];

        // preload normalized name map to reduce query overhead
        $foodsByName = Food::select('id', 'name')
            ->get()
            ->mapWithKeys(fn (Food $food) => [self::normalizeName($food->name) => $food->id])
            ->toArray();

        while (($row = fgetcsv($handle)) !== false) {
            try {
                $data = array_combine($header, $row);
                if (!$data) {
                    $stats['errors']++;
                    continue;
                }

                $title = trim((string) ($data['Title'] ?? ''));
                if ($title === '') {
                    $stats['skipped']++;
                    continue;
                }

                $normalizedTitle = self::normalizeName($data['Title Cleaned'] ?? $title);
                $foodId = $foodsByName[$normalizedTitle] ?? null;

                if (!$foodId) {
                    $foodId = Food::query()
                        ->whereRaw('LOWER(name) LIKE ?', ['%' . $normalizedTitle . '%'])
                        ->value('id');
                }

                if (!$foodId) {
                    $stats['unmatched']++;
                    continue;
                }

                Food::where('id', $foodId)->update([
                    'ingredients'       => self::nullIfEmpty($data['Ingredients'] ?? null),
                    'steps'             => self::nullIfEmpty($data['Steps'] ?? null),
                    'source_url'        => self::sanitizeUrl($data['URL'] ?? null),
                    'recipe_category'   => self::nullIfEmpty($data['Category'] ?? null),
                    'recipe_loves'      => self::parseInt($data['Loves'] ?? null),
                    'total_ingredients' => self::parseInt($data['Total Ingredients'] ?? null),
                    'total_steps'       => self::parseInt($data['Total Steps'] ?? null),
                    'recipe_synced_at'  => now(),
                ]);

                $stats['synced']++;
            } catch (Exception $e) {
                $stats['errors']++;
                Log::warning('NutritionImport: Recipe row parse error', [
                    'message' => $e->getMessage(),
                ]);
            }
        }

        fclose($handle);

        return $stats;
    }

    /**
     * Import ALL recipes rows from Indonesian_Food_Recipes.csv into food_recipes table.
     * Uses upsert by deterministic recipe_hash so it is safe to re-run.
     *
     * @return array{imported: int, updated: int, errors: int}
     */
    public static function importRecipesToTable(string $path = null): array
    {
        $path = $path ?? storage_path('app/dataset/Indonesian_Food_Recipes.csv');

        if (!file_exists($path)) {
            throw new Exception("CSV file not found: {$path}");
        }

        $handle = fopen($path, 'r');
        if (!$handle) {
            throw new Exception("Cannot open CSV file: {$path}");
        }

        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            throw new Exception('CSV file is empty or has no header row.');
        }

        $header = array_map('trim', $header);
        $stats = ['imported' => 0, 'updated' => 0, 'errors' => 0];

        $rows = [];
        $batchSize = 500;

        while (($row = fgetcsv($handle)) !== false) {
            try {
                $data = array_combine($header, $row);
                if (!$data) {
                    $stats['errors']++;
                    continue;
                }

                $title = self::nullIfEmpty($data['Title'] ?? null);
                if (!$title) {
                    $stats['errors']++;
                    continue;
                }

                $titleCleaned = self::nullIfEmpty($data['Title Cleaned'] ?? null);
                $sourceUrl = self::sanitizeUrl($data['URL'] ?? null);
                $hashSeed = implode('|', [
                    self::normalizeName($title),
                    self::normalizeName((string) ($titleCleaned ?? '')),
                    self::normalizeName((string) ($data['Category'] ?? '')),
                    self::normalizeName((string) ($sourceUrl ?? '')),
                ]);

                $rows[] = [
                    'recipe_hash' => hash('sha256', $hashSeed),
                    'title' => $title,
                    'title_cleaned' => $titleCleaned,
                    'ingredients' => self::nullIfEmpty($data['Ingredients'] ?? null),
                    'steps' => self::nullIfEmpty($data['Steps'] ?? null),
                    'loves' => self::parseInt($data['Loves'] ?? null),
                    'source_url' => $sourceUrl,
                    'category' => self::nullIfEmpty($data['Category'] ?? null),
                    'total_ingredients' => self::parseInt($data['Total Ingredients'] ?? null),
                    'total_steps' => self::parseInt($data['Total Steps'] ?? null),
                    'ingredients_cleaned' => self::nullIfEmpty($data['Ingredients Cleaned'] ?? null),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                if (count($rows) >= $batchSize) {
                    self::upsertRecipeBatch($rows, $stats);
                    $rows = [];
                }
            } catch (Exception $e) {
                $stats['errors']++;
                Log::warning('NutritionImport: Recipe import row error', [
                    'message' => $e->getMessage(),
                ]);
            }
        }

        if (!empty($rows)) {
            self::upsertRecipeBatch($rows, $stats);
        }

        fclose($handle);

        return $stats;
    }

    /**
     * Import all CSV files from receipt folder to food_recipes table.
     * Category is inferred from filename pattern: dataset-<category>.csv
     *
     * @return array{files: int, imported: int, updated: int, errors: int}
     */
    public static function importReceiptDirectoryToTable(string $directory = null): array
    {
        $directory = $directory ?? storage_path('app/dataset/receipt');

        if (!is_dir($directory)) {
            throw new Exception("Directory not found: {$directory}");
        }

        $files = glob(rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*.csv') ?: [];
        if (empty($files)) {
            throw new Exception("No CSV files found in directory: {$directory}");
        }

        $summary = ['files' => 0, 'imported' => 0, 'updated' => 0, 'errors' => 0];

        foreach ($files as $filePath) {
            $categoryFromFilename = self::extractCategoryFromFilename($filePath);
            $stats = self::importRecipesToTableWithForcedCategory($filePath, $categoryFromFilename);

            $summary['files']++;
            $summary['imported'] += $stats['imported'];
            $summary['updated'] += $stats['updated'];
            $summary['errors'] += $stats['errors'];
        }

        return $summary;
    }

    private static function parseFloat($value): float
    {
        $cleaned = preg_replace('/[^0-9.\-]/', '', (string) $value);
        return max(0, (float) $cleaned);
    }

    private static function sanitizeUrl(?string $url): ?string
    {
        if (empty($url)) {
            return null;
        }

        $url = trim($url);

        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        return null;
    }

    private static function parseInt($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $cleaned = preg_replace('/[^0-9]/', '', (string) $value);
        if ($cleaned === '') {
            return null;
        }

        return (int) $cleaned;
    }

    private static function normalizeName(?string $value): string
    {
        $value = mb_strtolower(trim((string) $value));
        $value = preg_replace('/[^a-z0-9\s]/', ' ', $value);
        $value = preg_replace('/\s+/', ' ', $value);
        return trim((string) $value);
    }

    private static function nullIfEmpty($value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    private static function upsertRecipeBatch(array $rows, array &$stats): void
    {
        $hashes = array_column($rows, 'recipe_hash');
        $existingCount = FoodRecipe::query()->whereIn('recipe_hash', $hashes)->count();

        FoodRecipe::upsert(
            $rows,
            ['recipe_hash'],
            [
                'title',
                'title_cleaned',
                'ingredients',
                'steps',
                'loves',
                'source_url',
                'category',
                'total_ingredients',
                'total_steps',
                'ingredients_cleaned',
                'updated_at',
            ]
        );

        $stats['updated'] += $existingCount;
        $stats['imported'] += max(0, count($rows) - $existingCount);
    }

    /**
     * Internal helper to import a single recipe CSV while forcing category.
     *
     * @return array{imported: int, updated: int, errors: int}
     */
    private static function importRecipesToTableWithForcedCategory(string $path, ?string $forcedCategory): array
    {
        if (!file_exists($path)) {
            throw new Exception("CSV file not found: {$path}");
        }

        $handle = fopen($path, 'r');
        if (!$handle) {
            throw new Exception("Cannot open CSV file: {$path}");
        }

        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            throw new Exception('CSV file is empty or has no header row.');
        }

        $header = array_map('trim', $header);
        $stats = ['imported' => 0, 'updated' => 0, 'errors' => 0];
        $rows = [];
        $batchSize = 500;

        while (($row = fgetcsv($handle)) !== false) {
            try {
                $data = array_combine($header, $row);
                if (!$data) {
                    $stats['errors']++;
                    continue;
                }

                $title = self::nullIfEmpty($data['Title'] ?? null);
                if (!$title) {
                    $stats['errors']++;
                    continue;
                }

                $titleCleaned = self::nullIfEmpty($data['Title Cleaned'] ?? null);
                $sourceUrl = self::sanitizeUrl($data['URL'] ?? null);
                $category = $forcedCategory ?: self::nullIfEmpty($data['Category'] ?? null);

                $hashSeed = implode('|', [
                    self::normalizeName($title),
                    self::normalizeName((string) ($titleCleaned ?? '')),
                    self::normalizeName((string) ($category ?? '')),
                    self::normalizeName((string) ($sourceUrl ?? '')),
                ]);

                $rows[] = [
                    'recipe_hash' => hash('sha256', $hashSeed),
                    'title' => $title,
                    'title_cleaned' => $titleCleaned,
                    'ingredients' => self::nullIfEmpty($data['Ingredients'] ?? null),
                    'steps' => self::nullIfEmpty($data['Steps'] ?? null),
                    'loves' => self::parseInt($data['Loves'] ?? null),
                    'source_url' => $sourceUrl,
                    'category' => $category,
                    'total_ingredients' => self::parseInt($data['Total Ingredients'] ?? null),
                    'total_steps' => self::parseInt($data['Total Steps'] ?? null),
                    'ingredients_cleaned' => self::nullIfEmpty($data['Ingredients Cleaned'] ?? null),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                if (count($rows) >= $batchSize) {
                    self::upsertRecipeBatch($rows, $stats);
                    $rows = [];
                }
            } catch (Exception $e) {
                $stats['errors']++;
                Log::warning('NutritionImport: Receipt import row error', [
                    'file' => $path,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        if (!empty($rows)) {
            self::upsertRecipeBatch($rows, $stats);
        }

        fclose($handle);

        return $stats;
    }

    private static function extractCategoryFromFilename(string $path): ?string
    {
        $name = pathinfo($path, PATHINFO_FILENAME); // e.g., dataset-ayam
        if (preg_match('/^dataset-(.+)$/', $name, $matches)) {
            return self::normalizeName($matches[1]);
        }

        return null;
    }
}
