<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class IbuHamilSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $ibuHamilNames = [
            'Siti Aminah',
            'Ratna Sari',
            'Dewi Lestari',
            'Maya Safira',
            'Putri Ayu',
            'Rina Wati',
            'Wulan Sari',
            'Fitri Handayani',
            'Anisa Rahma',
            'Indah Permata',
            'Riska Amalia',
            'Citra Kirana',
            'Nurul Hidayah',
            'Siska Amelia',
            'Dina Melati',
            'Rina Marlina',
            'Tika Pratiwi',
            'Yuni Astuti',
            'Lestari Ayu',
            'Mega Putri'
        ];

        foreach ($ibuHamilNames as $index => $name) {
            $email = strtolower(str_replace(' ', '.', $name)) . '@prenava.com';

            // Use DB::table for firstOrCreate to ensure user_id is always available
            $user = DB::table('users')->where('email', $email)->first();

            if (!$user) {
                $userId = DB::table('users')->insertGetId([
                    'name'       => $name,
                    'email'      => $email,
                    'password'   => Hash::make('password123'),
                    'role'       => 'ibu_hamil',
                    'is_active'  => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ], 'user_id');
            } else {
                $userId = $user->user_id;
            }

            DB::table('user_profile')->updateOrInsert(
                ['user_id' => $userId],
                [
                    'photo'                => 'profile/photo.jpg',
                    'tanggal_lahir'        => $this->randomBirthdate(),
                    'alamat'               => $this->randomAddress(),
                    'usia'                 => rand(20, 38),
                    'no_telepon'           => '08' . rand(100000000, 999999999),
                    'pendidikan_terakhir'  => $this->randomEducation(),
                    'pekerjaan'            => $this->randomJob(),
                    'golongan_darah'       => $this->randomBloodType(),
                    'updated_at'           => $now,
                ]
            );
        }

        $this->command->info('Successfully created 20 ibu hamil users.');
    }

    private function randomBirthdate()
    {
        $year = rand(1986, 2004);
        $month = rand(1, 12);
        $day = rand(1, 28);

        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    private function randomAddress()
    {
        $streets = [
            'Jl. Melati No.',
            'Jl. Mawar No.',
            'Jl. Anggrek No.',
            'Jl. Dahlia No.',
            'Jl. Kenanga No.',
        ];

        $street = $streets[array_rand($streets)];
        $number = rand(1, 100);

        return $street . ' ' . $number;
    }

    private function randomEducation()
    {
        $educations = ['SMA', 'S1', 'D3', 'S2'];

        return $educations[array_rand($educations)];
    }

    private function randomJob()
    {
        $jobs = [
            'Ibu Rumah Tangga',
            'Guru',
            'Perawat',
            'Wiraswasta',
            'Karyawan Swasta',
            'PNS',
            'Akuntan',
            'Desainer',
        ];

        return $jobs[array_rand($jobs)];
    }

    private function randomBloodType()
    {
        $bloodTypes = ['A', 'B', 'AB', 'O'];

        return $bloodTypes[array_rand($bloodTypes)];
    }
}
