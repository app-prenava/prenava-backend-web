<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Pregnancy;
use App\Models\StuntingPrediction;
use App\Models\HealthScanHistory;
use App\Models\PregnancyAssessment;
use App\Services\MaternalTriageService;

class MaternalTriageSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $triageService = app(MaternalTriageService::class);

        $patientsData = [
            [
                'name'         => 'Siti Aminah',
                'email'        => 'siti.aminah@prenava.com',
                'gestational'  => 32,
                'stunting_p'   => 0.86,
                'stunting_c'   => 1,
                'anemia_label' => 'anemia',
                'anemia_conf'  => 0.89,
                'hypertension' => true,
                'complications'=> true,
                'expected_cat' => 'MERAH',
            ],
            [
                'name'         => 'Ratna Sari',
                'email'        => 'ratna.sari@prenava.com',
                'gestational'  => 28,
                'stunting_p'   => 0.76,
                'stunting_c'   => 1,
                'anemia_label' => 'anemia',
                'anemia_conf'  => 0.84,
                'hypertension' => false,
                'complications'=> true,
                'expected_cat' => 'MERAH',
            ],
            [
                'name'         => 'Dewi Lestari',
                'email'        => 'dewi.lestari@prenava.com',
                'gestational'  => 22,
                'stunting_p'   => 0.54,
                'stunting_c'   => 1,
                'anemia_label' => 'non_anemia',
                'anemia_conf'  => 0.78,
                'hypertension' => false,
                'complications'=> false,
                'expected_cat' => 'KUNING',
            ],
            [
                'name'         => 'Maya Safira',
                'email'        => 'maya.safira@prenava.com',
                'gestational'  => 18,
                'stunting_p'   => 0.44,
                'stunting_c'   => 0,
                'anemia_label' => 'anemia',
                'anemia_conf'  => 0.62,
                'hypertension' => false,
                'complications'=> false,
                'expected_cat' => 'KUNING',
            ],
            [
                'name'         => 'Putri Ayu',
                'email'        => 'putri.ayu@prenava.com',
                'gestational'  => 14,
                'stunting_p'   => 0.12,
                'stunting_c'   => 0,
                'anemia_label' => 'non_anemia',
                'anemia_conf'  => 0.94,
                'hypertension' => false,
                'complications'=> false,
                'expected_cat' => 'HIJAU',
            ],
        ];

        foreach ($patientsData as $p) {
            // 1. Dapatkan atau buat akun User
            $user = User::where('email', $p['email'])->first();
            if (! $user) {
                $userId = DB::table('users')->insertGetId([
                    'name'       => $p['name'],
                    'email'      => $p['email'],
                    'password'   => Hash::make('password123'),
                    'role'       => 'ibu_hamil',
                    'is_active'  => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ], 'user_id');
                $user = User::find($userId);
            }

            // 2. Buat atau update rekam kehamilan
            $pregnancy = Pregnancy::where('user_id', $user->user_id)->first();
            if (! $pregnancy) {
                $pregnancy = Pregnancy::create([
                    'user_id'               => $user->user_id,
                    'lmp_date'              => $now->copy()->subWeeks($p['gestational'])->toDateString(),
                    'gestational_age_weeks' => $p['gestational'],
                    'status'                => 'ongoing',
                    'multiple_gestation'    => false,
                ]);
            } else {
                $pregnancy->update([
                    'gestational_age_weeks' => $p['gestational'],
                    'status'                => 'ongoing',
                ]);
            }

            // 3. Simpan data observasi klinis (Pregnancy Assessment)
            PregnancyAssessment::updateOrCreate(
                ['pregnancy_id' => $pregnancy->pregnancy_id],
                [
                    'bmi'                    => 24.5,
                    'hypertension'           => $p['hypertension'],
                    'is_diabetes'            => false,
                    'gestational_diabetes'   => false,
                    'is_fever'               => false,
                    'previous_complications' => $p['complications'],
                    'placenta_previa'        => false,
                ]
            );

            // 4. Simpan riwayat prediksi stunting
            StuntingPrediction::updateOrCreate(
                ['user_id' => $user->user_id],
                [
                    'input_data'      => ['gestational_week' => $p['gestational']],
                    'ml_payload'      => ['prob' => $p['stunting_p']],
                    'probability'     => $p['stunting_p'],
                    'prediction'      => $p['stunting_c'],
                    'risk_label'      => $p['stunting_p'] >= 0.7 ? 'high_risk' : ($p['stunting_p'] >= 0.4 ? 'moderate_risk' : 'low_risk'),
                    'status'          => StuntingPrediction::STATUS_SUCCESS,
                    'model_version'   => 'v2.1.0-lightgbm',
                    'latency_ms'      => 45,
                ]
            );

            // 5. Simpan riwayat deteksi anemia
            HealthScanHistory::updateOrCreate(
                ['user_id' => $user->user_id, 'type' => 'anemia'],
                [
                    'result'     => [
                        'label'        => $p['anemia_label'],
                        'confidence'   => $p['anemia_conf'],
                        'roi_detected' => true,
                    ],
                    'image_path' => null,
                ]
            );

            // 6. Eksekusi kalkulasi triase komposit melalui MaternalTriageService
            $scoreRecord = $triageService->calculatePatientTriage($user->user_id, $pregnancy->pregnancy_id);

            $this->command->info("Seeded Triage for {$p['name']}: Score {$scoreRecord->triage_score} [{$scoreRecord->triage_category}]");
        }
    }
}
