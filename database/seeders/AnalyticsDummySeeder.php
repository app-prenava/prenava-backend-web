<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AnalyticsDummySeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        // 1. Ambil semua user_id ibu hamil
        $ibuHamilUsers = DB::table('users')
            ->where('role', 'ibu_hamil')
            ->pluck('user_id');

        if ($ibuHamilUsers->isEmpty()) {
            $this->command->warn('Tidak ada user ibu hamil untuk diseed analytics.');
            return;
        }

        $this->command->info('Seeding pregnancies & analytics dummy data for ' . $ibuHamilUsers->count() . ' users...');

        // 2. Seed Pregnancies (Distribusi Usia Kehamilan & LMP)
        DB::table('pregnancies')->delete();
        
        $weekSamples = [6, 10, 12, 14, 16, 18, 20, 22, 24, 26, 28, 30, 32, 34, 36, 38];
        foreach ($ibuHamilUsers as $idx => $userId) {
            $weeks = $weekSamples[$idx % count($weekSamples)];
            $lmpDate = $now->copy()->subWeeks($weeks)->subDays(rand(0, 5));

            DB::table('pregnancies')->insert([
                'user_id' => $userId,
                'lmp_date' => $lmpDate->format('Y-m-d'),
                'gestational_age_weeks' => $weeks,
                'status' => 'ongoing',
                'multiple_gestation' => ($idx % 7 === 0) ? 1 : 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
        $this->command->info('Seeded ' . DB::table('pregnancies')->count() . ' pregnancies records.');

        // 3. Seed Stunting Predictions
        DB::table('stunting_predictions')->delete();

        foreach ($ibuHamilUsers as $idx => $userId) {
            $isHighRisk = ($idx % 3 === 0); // 33% resiko tinggi
            $probability = $isHighRisk ? (0.65 + (rand(0, 30) / 100)) : (0.05 + (rand(0, 35) / 100));
            $predClass = $isHighRisk ? 1 : 0;
            $riskLabel = $isHighRisk ? 'Tinggi' : 'Rendah';

            DB::table('stunting_predictions')->insert([
                'user_id' => $userId,
                'input_data' => json_encode([
                    'tinggi_badan_ibu' => rand(145, 168),
                    'berat_badan_ibu' => rand(45, 75),
                    'lingkar_lengan' => rand(21, 30),
                    'usia_kehamilan' => rand(12, 36),
                ]),
                'ml_payload' => json_encode(['features' => [rand(1, 10), rand(1, 5)]]),
                'ml_response' => json_encode(['prediction' => $predClass, 'probability' => $probability]),
                'probability' => round($probability, 4),
                'prediction' => $predClass,
                'risk_label' => $riskLabel,
                'status' => 'success',
                'created_at' => $now->copy()->subDays(rand(1, 45)),
                'updated_at' => $now,
            ]);
        }
        $this->command->info('Seeded ' . DB::table('stunting_predictions')->count() . ' stunting predictions.');

        // 4. Seed Health Scan Histories (Anemia & Depression)
        DB::table('health_scan_histories')->delete();

        foreach ($ibuHamilUsers as $idx => $userId) {
            // Record Anemia
            $isAnemia = ($idx % 2 === 0);
            $anemiaResult = [
                'label' => $isAnemia ? 'anemia' : 'non-anemia',
                'confidence' => round($isAnemia ? 0.85 + (rand(0, 12) / 100) : 0.90 + (rand(0, 8) / 100), 2),
                'threshold_used' => 0.5,
                'probability_anemia' => $isAnemia ? 0.85 : 0.10,
                'probability_non_anemia' => $isAnemia ? 0.15 : 0.90,
            ];

            DB::table('health_scan_histories')->insert([
                'user_id' => $userId,
                'type' => 'anemia',
                'result' => json_encode($anemiaResult),
                'image_path' => 'scans/anemia_sample_' . ($idx + 1) . '.jpg',
                'created_at' => $now->copy()->subDays(rand(1, 30)),
                'updated_at' => $now,
            ]);

            // Record Depression
            $isDepressed = ($idx % 4 === 0); // 25% terdeteksi indikator depresi
            $depressionResult = [
                'level' => $isDepressed ? 'Terdapat beberapa indikator depresi' : 'Indikator depresi tidak signifikan',
                'score' => $isDepressed ? rand(55, 80) : rand(10, 35),
                'expression' => [
                    'score' => $isDepressed ? 65 : 20,
                    'probabilities' => [
                        'Sad' => $isDepressed ? 40 : 5,
                        'Neutral' => 35,
                        'Happy' => $isDepressed ? 10 : 50,
                        'Angry' => $isDepressed ? 5 : 2,
                        'Fear' => $isDepressed ? 5 : 3,
                        'Disgust' => 2,
                        'Surprise' => 3,
                    ],
                ],
                'face_detected' => true,
                'face_confidence' => 0.98,
                'success' => true,
                'disclaimer' => 'Hasil ini hanya skrining awal dan bukan diagnosis medis.',
            ];

            DB::table('health_scan_histories')->insert([
                'user_id' => $userId,
                'type' => 'depression',
                'result' => json_encode($depressionResult),
                'image_path' => 'scans/depression_sample_' . ($idx + 1) . '.jpg',
                'created_at' => $now->copy()->subDays(rand(1, 30)),
                'updated_at' => $now,
            ]);
        }

        $this->command->info('Seeded ' . DB::table('health_scan_histories')->count() . ' health scan histories (anemia & depression).');
    }
}
