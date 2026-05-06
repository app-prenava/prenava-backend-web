<?php

namespace App\Services;

use App\Models\Food;
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
}
