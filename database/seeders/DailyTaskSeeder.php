<?php

namespace Database\Seeders;

use App\Models\DailyTask;
use Illuminate\Database\Seeder;

class DailyTaskSeeder extends Seeder
{
    public function run(): void
    {
        $tasks = [
            // ── Manual Tasks (Umum) ───────────────────────────────────────
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
                'target_category' => 'nutrition_risk',
            ],
            [
                'title'           => 'Cek Prediksi Depresi',
                'description'     => 'Pantau kesehatan mental Bunda dengan fitur deteksi depresi.',
                'task_type'       => 'feature_depresi',
                'points'          => 10,
                'target_category' => 'mental_support',
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
                'target_category' => 'mental_support',
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
                'description'     => 'Dapatkan info gizi harian dan tanda bahaya kehamilan di menu Tips.',
                'task_type'       => 'feature_tips',
                'points'          => 10,
                'target_category' => 'low_literacy',
            ],
            [
                'title'           => 'Cek Risiko Stunting',
                'description'     => 'Lakukan skrining risiko stunting untuk memantau gizi & kesehatan anak.',
                'task_type'       => 'feature_stunting',
                'points'          => 10,
                'target_category' => 'nutrition_risk',
            ],
            [
                'title'           => 'Cek Kualitas Udara',
                'description'     => 'Pastikan lingkungan Bunda sehat dengan cek kualitas udara.',
                'task_type'       => 'feature_udara',
                'points'          => 5,
                'target_category' => 'balanced',
            ],
            [
                'title'           => 'Eksplor Kearifan Lokal',
                'description'     => 'Pahami mitos vs fakta kehamilan dari berbagai daerah.',
                'task_type'       => 'feature_local_wisdom',
                'points'          => 10,
                'target_category' => 'low_literacy',
            ],
            [
                'title'           => 'Catat Minum di Rekomendasi Hidrasi',
                'description'     => 'Pantau kecukupan cairan harian Bunda.',
                'task_type'       => 'feature_hidrasi',
                'points'          => 5,
                'target_category' => null,
            ],
            [
                'title'           => 'Cek Jadwal Janji Temu Bidan',
                'description'     => 'Pastikan jadwal kontrol kehamilan Bunda bersama bidan tetap rutin.',
                'task_type'       => 'feature_appointment',
                'points'          => 10,
                'target_category' => 'anc_monitoring',
            ],
            [
                'title'           => 'Perbarui Catatan Kunjungan',
                'description'     => 'Catat hasil pemeriksaan kehamilan agar mudah dipantau.',
                'task_type'       => 'feature_kunjungan',
                'points'          => 10,
                'target_category' => 'anc_monitoring',
            ],
            [
                'title'           => 'Lihat Peta Bidan Terdekat',
                'description'     => 'Kenali lokasi bidan terpercaya di sekitar tempat tinggal Bunda.',
                'task_type'       => 'feature_bidan_map',
                'points'          => 10,
                'target_category' => 'anc_monitoring',
            ],
        ];

        foreach ($tasks as $task) {
            DailyTask::updateOrCreate(['task_type' => $task['task_type']], $task);
        }
    }
}
