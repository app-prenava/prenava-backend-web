<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class DataMlSportSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        $activities = [
            ['code' => 'walking',                 'name' => 'Jalan Santai'],
            ['code' => 'morning_walk',             'name' => 'Jalan Pagi'],
            ['code' => 'evening_walk',             'name' => 'Jalan Sore'],
            ['code' => 'brisk_walking',            'name' => 'Jalan Cepat'],
            ['code' => 'treadmill_walking',        'name' => 'Jalan di Treadmill'],
            ['code' => 'marching_in_place',        'name' => 'Jalan di Tempat'],
            ['code' => 'prenatal_exercise',        'name' => 'Senam Hamil'],
            ['code' => 'prenatal_yoga',            'name' => 'Yoga Hamil'],
            ['code' => 'chair_yoga',               'name' => 'Yoga Duduk'],
            ['code' => 'prenatal_pilates',         'name' => 'Pilates Hamil'],
            ['code' => 'stretching',               'name' => 'Peregangan'],
            ['code' => 'back_stretch_routine',     'name' => 'Peregangan Punggung'],
            ['code' => 'leg_stretch_routine',      'name' => 'Peregangan Kaki'],
            ['code' => 'breathing_exercises',      'name' => 'Latihan Pernapasan'],
            ['code' => 'kegel',                    'name' => 'Senam Kegel'],
            ['code' => 'pelvic_tilt',              'name' => 'Latihan Panggul'],
            ['code' => 'cat_cow_modified',         'name' => 'Gerakan Cat-Cow'],
            ['code' => 'bird_dog_modified',        'name' => 'Gerakan Bird Dog'],
            ['code' => 'mobility_flow',            'name' => 'Latihan Mobilitas'],
            ['code' => 'light_home_aerobics',      'name' => 'Senam Ringan di Rumah'],
            ['code' => 'low_impact_aerobics',      'name' => 'Senam Low Impact'],
            ['code' => 'low_impact_zumba',         'name' => 'Zumba Ringan'],
            ['code' => 'light_dance',              'name' => 'Tari Ringan'],
            ['code' => 'stationary_cycling',       'name' => 'Sepeda Statis'],
            ['code' => 'casual_cycling',           'name' => 'Bersepeda Santai'],
            ['code' => 'swimming',                 'name' => 'Berenang'],
            ['code' => 'water_aerobics',           'name' => 'Senam Air'],
            ['code' => 'aqua_walking',             'name' => 'Jalan di Air'],
            ['code' => 'side_leg_raise',           'name' => 'Angkat Kaki Samping'],
            ['code' => 'wall_pushups',             'name' => 'Push-Up Dinding'],
            ['code' => 'seated_upper_body',        'name' => 'Latihan Tubuh Bagian Atas Duduk'],
            ['code' => 'resistance_band_light',    'name' => 'Latihan Resistance Band Ringan'],
            ['code' => 'supported_squats',         'name' => 'Squat dengan Bantuan'],
            ['code' => 'sit_to_stand_slow',        'name' => 'Duduk Berdiri Perlahan'],
            ['code' => 'light_stair_climbing',     'name' => 'Naik Turun Tangga Ringan'],
            ['code' => 'light_household_activity', 'name' => 'Aktivitas Rumah Tangga Ringan'],
            ['code' => 'light_gardening',          'name' => 'Berkebun Ringan'],
            ['code' => 'light_morning_exercise',   'name' => 'Senam Pagi Ringan'],
            ['code' => 'casual_badminton',         'name' => 'Badminton Santai'],
            ['code' => 'jogging',                  'name' => 'Jogging'],
            ['code' => 'running',                  'name' => 'Lari'],
            ['code' => 'jump_rope',                'name' => 'Lompat Tali'],
            ['code' => 'futsal',                   'name' => 'Futsal'],
            ['code' => 'basketball',               'name' => 'Basket'],
            ['code' => 'volleyball',               'name' => 'Voli'],
            ['code' => 'soccer',                   'name' => 'Sepak Bola'],
            ['code' => 'competitive_badminton',    'name' => 'Badminton Kompetitif'],
            ['code' => 'martial_arts',             'name' => 'Bela Diri'],
            ['code' => 'contact_sports',           'name' => 'Olahraga Kontak'],
            ['code' => 'heavy_weightlifting',      'name' => 'Angkat Beban Berat'],
        ];

        $data = array_map(fn ($item) => array_merge($item, [
            'video_link' => null,
            'long_text'  => null,
            'picture_1'  => null,
            'picture_2'  => null,
            'picture_3'  => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]), $activities);

        DB::table('data_ml_sport')->upsert(
            $data,
            ['code'],                         
            ['name', 'updated_at']            
        );

        $this->command->info('DataMlSportSeeder: ' . count($data) . ' activities seeded.');
    }
}