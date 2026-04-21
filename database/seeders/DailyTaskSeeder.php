<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\DailyTask;

class DailyTaskSeeder extends Seeder
{
    public function run(): void
    {
        $tasks = [
            // ── Manual tasks (user-initiated) ──────────────────────────────
            [
                'title'           => 'Minum 8 Gelas Air',
                'description'     => 'Pastikan tubuh tetap terhidrasi dengan baik seharian.',
                'task_type'       => 'hydration',
                'points'          => 10,
                'target_category' => null,
            ],
            [
                'title'           => 'Minum Tablet Tambah Darah',
                'description'     => 'Jangan lupa konsumsi suplemen harianmu.',
                'task_type'       => 'supplement',
                'points'          => 15,
                'target_category' => 'ibu_hamil',
            ],
            [
                'title'           => 'Jalan Kaki 15 Menit',
                'description'     => 'Aktivitas fisik ringan untuk kebugaran tubuh.',
                'task_type'       => 'exercise',
                'points'          => 20,
                'target_category' => null,
            ],
            [
                'title'           => 'Membaca Artikel Edukasi',
                'description'     => 'Baca minimal 1 artikel mengenai persalinan atau kehamilan.',
                'task_type'       => 'learning',
                'points'          => 5,
                'target_category' => null,
            ],
            [
                'title'           => 'Senam Kegel',
                'description'     => 'Lakukan senam kegel untuk memperkuat otot panggul.',
                'task_type'       => 'exercise',
                'points'          => 15,
                'target_category' => 'ibu_hamil',
            ],

            // ── Auto-tracked: Feature Usage ────────────────────────────────
            // Dicatat otomatis ketika user membuka fitur terkait di aplikasi.
            [
                'title'           => 'Cek Rekomendasi Olahraga',
                'description'     => 'Kamu membuka fitur Rekomendasi Olahraga hari ini.',
                'task_type'       => 'feature_olahraga',
                'points'          => 5,
                'target_category' => null,
            ],
            [
                'title'           => 'Pantau Hidrasi Harian',
                'description'     => 'Kamu membuka fitur Rekomendasi Hidrasi hari ini.',
                'task_type'       => 'feature_hidrasi',
                'points'          => 5,
                'target_category' => null,
            ],
            [
                'title'           => 'Baca Tips & Gizi',
                'description'     => 'Kamu mengakses Tips & Gizi hari ini.',
                'task_type'       => 'feature_tips',
                'points'          => 5,
                'target_category' => null,
            ],
            [
                'title'           => 'Cek Kalkulator HPL',
                'description'     => 'Kamu mengecek Kalkulator HPL hari ini.',
                'task_type'       => 'feature_kalkulator_hpl',
                'points'          => 5,
                'target_category' => null,
            ],
            [
                'title'           => 'Prediksi Anemia',
                'description'     => 'Kamu menggunakan fitur Prediksi Anemia hari ini.',
                'task_type'       => 'feature_anemia',
                'points'          => 10,
                'target_category' => null,
            ],
            [
                'title'           => 'Prediksi Depresi',
                'description'     => 'Kamu menggunakan fitur Prediksi Depresi hari ini.',
                'task_type'       => 'feature_depresi',
                'points'          => 10,
                'target_category' => null,
            ],
            [
                'title'           => 'Lihat Komunitas',
                'description'     => 'Kamu berinteraksi di Komunitas hari ini.',
                'task_type'       => 'feature_komunitas',
                'points'          => 5,
                'target_category' => null,
            ],
            [
                'title'           => 'Cek Kunjungan Bidan',
                'description'     => 'Kamu mengakses catatan Kunjungan Bidan hari ini.',
                'task_type'       => 'feature_kunjungan',
                'points'          => 5,
                'target_category' => null,
            ],
            [
                'title'           => 'Buat Janji Bidan',
                'description'     => 'Kamu menggunakan fitur Janji Temu Bidan hari ini.',
                'task_type'       => 'feature_appointment',
                'points'          => 10,
                'target_category' => null,
            ],
            [
                'title'           => 'Baca Kearifan Lokal',
                'description'     => 'Kamu membaca Kearifan Lokal (mitos kehamilan) hari ini.',
                'task_type'       => 'feature_local_wisdom',
                'points'          => 5,
                'target_category' => null,
            ],
        ];

        foreach ($tasks as $task) {
            DailyTask::firstOrCreate(['task_type' => $task['task_type']], $task);
        }
    }
}
