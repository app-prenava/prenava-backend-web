<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\DailyTask;

class DailyTaskSeeder extends Seeder
{
    public function run(): void
    {
        $tasks = [
            // ── Manual Tasks ──────────────────────────────────────────────
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

            // ── Auto-tracked: Feature Usage (Misi Fitur App) ──────────────
            [
                'title'           => 'Cek Prediksi Anemia',
                'description'     => 'Gunakan fitur deteksi anemia untuk pantau kesehatan darah.',
                'task_type'       => 'feature_anemia',
                'points'          => 10,
                'target_category' => null,
            ],
            [
                'title'           => 'Cek Prediksi Depresi',
                'description'     => 'Pantau kesehatan mental Bunda dengan fitur deteksi depresi.',
                'task_type'       => 'feature_depresi',
                'points'          => 10,
                'target_category' => null,
            ],
            [
                'title'           => 'Rekomendasi Olahraga',
                'description'     => 'Lihat gerakan olahraga yang aman untuk ibu hamil hari ini.',
                'task_type'       => 'feature_olahraga',
                'points'          => 5,
                'target_category' => null,
            ],
            [
                'title'           => 'Interaksi di Komunitas',
                'description'     => 'Buka menu komunitas untuk berbagi pengalaman dengan ibu lain.',
                'task_type'       => 'feature_komunitas',
                'points'          => 5,
                'target_category' => null,
            ],
            [
                'title'           => 'Pantau Kalkulator HPL',
                'description'     => 'Cek perkembangan janin dan estimasi kelahiran Bunda.',
                'task_type'       => 'feature_kalkulator_hpl',
                'points'          => 5,
                'target_category' => null,
            ],
            [
                'title'           => 'Baca Tips & Gizi',
                'description'     => 'Dapatkan info gizi harian Bunda di menu Tips.',
                'task_type'       => 'feature_tips',
                'points'          => 5,
                'target_category' => null,
            ],
            [
                'title'           => 'Cek Kualitas Udara',
                'description'     => 'Pastikan lingkungan Bunda sehat dengan cek kualitas udara.',
                'task_type'       => 'feature_udara',
                'points'          => 5,
                'target_category' => null,
            ],
            [
                'title'           => 'Eksplor Kearifan Lokal',
                'description'     => 'Pahami mitos-mitos kehamilan dari berbagai daerah.',
                'task_type'       => 'feature_local_wisdom',
                'points'          => 5,
                'target_category' => null,
            ],
        ];

        foreach ($tasks as $task) {
            DailyTask::updateOrCreate(['task_type' => $task['task_type']], $task);
        }
    }
}
