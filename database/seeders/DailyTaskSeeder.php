<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\DailyTask;

class DailyTaskSeeder extends Seeder
{
    public function run(): void
    {
        $tasks = [
            [
                'title' => 'Minum 8 Gelas Air',
                'description' => 'Pastikan tubuh tetap terhidrasi dengan baik seharian.',
                'task_type' => 'hydration',
                'points' => 10,
                'target_category' => null, // Berlaku untuk semua role
            ],
            [
                'title' => 'Minum Tablet Tambah Darah',
                'description' => 'Jangan lupa konsumsi suplemen harianmu.',
                'task_type' => 'supplement',
                'points' => 15,
                'target_category' => 'ibu_hamil',
            ],
            [
                'title' => 'Jalan Kaki 15 Menit',
                'description' => 'Aktivitas fisik ringan untuk kebugaran tubuh.',
                'task_type' => 'exercise',
                'points' => 20,
                'target_category' => null,
            ],
            [
                'title' => 'Membaca Artikel Edukasi',
                'description' => 'Baca minimal 1 artikel mengenai persalinan atau kehamilan.',
                'task_type' => 'learning',
                'points' => 5,
                'target_category' => null,
            ],
            [
                'title' => 'Senam Kegel',
                'description' => 'Lakukan senam kegel untuk memperkuat otot panggul.',
                'task_type' => 'exercise',
                'points' => 15,
                'target_category' => 'ibu_hamil',
            ]
        ];

        foreach ($tasks as $task) {
            DailyTask::create($task);
        }
    }
}
