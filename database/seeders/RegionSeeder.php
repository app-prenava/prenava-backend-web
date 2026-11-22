<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RegionSeeder extends Seeder
{
    public function run(): void
    {
        $basePath = database_path('seeders/data/regions');

        $provinces = json_decode(file_get_contents($basePath . '/provinces.json'), true);
        if (!empty($provinces)) {
            foreach (array_chunk($provinces, 100) as $chunk) {
                DB::table('provinces')->upsert($chunk, ['code'], ['name']);
            }
        }

        $regencies = json_decode(file_get_contents($basePath . '/regencies.json'), true);
        if (!empty($regencies)) {
            foreach (array_chunk($regencies, 200) as $chunk) {
                DB::table('regencies')->upsert($chunk, ['code'], ['province_code', 'name']);
            }
        }

        $districts = json_decode(file_get_contents($basePath . '/districts.json'), true);
        if (!empty($districts)) {
            foreach (array_chunk($districts, 500) as $chunk) {
                DB::table('districts')->upsert($chunk, ['code'], ['regency_code', 'name']);
            }
        }
    }
}

