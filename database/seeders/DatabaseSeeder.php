<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        // Admin Prenava
        User::firstOrCreate(
            ['email' => 'admin@prenava.com'],
            [
                'name'      => 'Admin Prenava',
                'password'  => Hash::make('password123'),
                'role'      => 'admin',
                'is_active' => true,
            ]
        );

        // Admin Dinkes
        User::firstOrCreate(
            ['email' => 'dinkes@prenava.com'],
            [
                'name'      => 'Admin Dinkes',
                'password'  => Hash::make('password123'),
                'role'      => 'dinkes',
                'is_active' => true,
            ]
        );

        $adminDinkesId = DB::table('users')->where('email', 'dinkes@prenava.com')->value('user_id');

        DB::table('user_dinkes')->updateOrInsert(
            ['user_id' => $adminDinkesId],
            [
                'photo'      => 'profiles/dinkes/photo.jpg',
                'jabatan'    => 'Keuangan',
                'nip'        => '1234567890',
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        // Ibu Hamil
        User::firstOrCreate(
            ['email' => 'hamil@prenava.com'],
            [
                'name'      => 'User Hamil',
                'password'  => Hash::make('password123'),
                'role'      => 'ibu_hamil',
                'is_active' => true,
            ]
        );

        $ibuHamilId = DB::table('users')->where('email', 'hamil@prenava.com')->value('user_id');

        DB::table('user_profile')->updateOrInsert(
            ['user_id' => $ibuHamilId],
            [
                'photo'               => 'profiles/ibu/photo.jpg',
                'tanggal_lahir'       => '1995-03-15',
                'alamat'              => 'Jl. Melati No. 10',
                'usia'                => 29,
                'no_telepon'          => null,
                'pendidikan_terakhir' => null,
                'pekerjaan'           => null,
                'golongan_darah'      => null,
                'created_at'          => $now,
                'updated_at'          => $now,
            ]
        );

        // Bidan
        User::firstOrCreate(
            ['email' => 'bidan.rita@prenava.com'],
            [
                'name'      => 'Bidan Rita',
                'password'  => Hash::make('password123'),
                'role'      => 'bidan',
                'is_active' => true,
            ]
        );

        $bidanId = DB::table('users')->where('email', 'bidan.rita@prenava.com')->value('user_id');

        DB::table('bidan_profile')->updateOrInsert(
            ['user_id' => $bidanId],
            [
                'photo'                    => 'profiles/bidan/photo.jpg',
                'tempat_praktik'           => 'Klinik Sehati',
                'alamat_praktik'           => 'Jl. Mawar No. 5',
                'kota_tempat_praktik'      => 'Bandung',
                'kecamatan_tempat_praktik' => 'Coblong',
                'telepon_tempat_praktik'   => '081234567890',
                'spesialisasi'             => 'Kebidanan Umum',
                'created_at'               => $now,
                'updated_at'               => $now,
            ]
        );

        $this->call([
            IconSeeder::class,
            KomunitasSeeder::class,
            PostpartumArticlesSeeder::class,
            SaranMakananSeeder::class,
            ProductSeeder::class,
            IbuHamilSeeder::class,
            KomunitasPostSeeder::class,
            CatatanKunjunganSeeder::class,
            ShopSeeder::class,
            ShopReviewSeeder::class,
            KomunitasLikeSeeder::class,
            PregnancyTipsSeeder::class,
            SubscriptionPlanSeeder::class,
        ]);
    }
}
