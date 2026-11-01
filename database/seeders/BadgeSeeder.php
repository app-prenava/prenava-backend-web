<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Badge;

class BadgeSeeder extends Seeder
{
    public function run(): void
    {
        $badges = [
            [
                'badge_code'             => 'badge_ttd_master',
                'name'                   => 'Bunda Taat TTD',
                'category'               => 'medis',
                'description'            => 'Mencatat minum Tablet Tambah Darah selama 14 hari tanpa putus.',
                'icon'                   => 'medication',
                'bonus_points'           => 50,
                'requirement_type'       => 'ttd_count',
                'requirement_threshold'  => 14,
                'is_active'              => true,
            ],
            [
                'badge_code'             => 'badge_hydration_champ',
                'name'                   => 'Juara Hidrasi 8 Gelas',
                'category'               => 'hidrasi',
                'description'            => 'Memenuhi target 8/8 gelas air per hari selama 7 hari berturut-turut.',
                'icon'                   => 'water_drop',
                'bonus_points'           => 40,
                'requirement_type'       => 'hydration_streak',
                'requirement_threshold'  => 7,
                'is_active'              => true,
            ],
            [
                'badge_code'             => 'badge_nutrition_plate',
                'name'                   => 'Piring Gizi Pelangi',
                'category'               => 'nutrisi',
                'description'            => 'Melakukan verifikasi Food Vision AI dengan skor kepatuhan ≥ 70% sebanyak 10 kali.',
                'icon'                   => 'restaurant',
                'bonus_points'           => 50,
                'requirement_type'       => 'food_compliance_count',
                'requirement_threshold'  => 10,
                'is_active'              => true,
            ],
            [
                'badge_code'             => 'badge_active_mom',
                'name'                   => 'Bunda Bugar & Sehat',
                'category'               => 'aktivitas',
                'description'            => 'Menyelesaikan misi jalan santai / senam hamil sebanyak 12 kali.',
                'icon'                   => 'directions_walk',
                'bonus_points'           => 30,
                'requirement_type'       => 'exercise_count',
                'requirement_threshold'  => 12,
                'is_active'              => true,
            ],
            [
                'badge_code'             => 'badge_streak_7',
                'name'                   => 'Konsistensi Seminggu',
                'category'               => 'streak',
                'description'            => 'Mempertahankan hari beruntun (streak) selama 7 hari penuh.',
                'icon'                   => 'local_fire_department',
                'bonus_points'           => 25,
                'requirement_type'       => 'streak_days',
                'requirement_threshold'  => 7,
                'is_active'              => true,
            ],
            [
                'badge_code'             => 'badge_streak_30',
                'name'                   => 'Bulan Penuh Dedikasi',
                'category'               => 'streak',
                'description'            => 'Mempertahankan hari beruntun (streak) selama 30 hari penuh.',
                'icon'                   => 'emoji_events',
                'bonus_points'           => 100,
                'requirement_type'       => 'streak_days',
                'requirement_threshold'  => 30,
                'is_active'              => true,
            ],
            [
                'badge_code'             => 'badge_early_bird',
                'name'                   => 'Awali Pagi Sehat',
                'category'               => 'gaya_hidup',
                'description'            => 'Menyelesaikan minimal 1 tugas sehat sebelum pukul 08:00 WIB.',
                'icon'                   => 'wb_sunny',
                'bonus_points'           => 15,
                'requirement_type'       => 'early_bird',
                'requirement_threshold'  => 1,
                'is_active'              => true,
            ],
        ];

        foreach ($badges as $badge) {
            Badge::updateOrCreate(
                ['badge_code' => $badge['badge_code']],
                $badge
            );
        }
    }
}
