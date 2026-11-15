<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\BidanLocation;

class BidanLocationSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $partnerBidans = [
            [
                'email'            => 'bidan.rita@prenava.com',
                'name'             => 'Bidan Rita, S.Tr.Keb',
                'tempat_praktik'   => 'PMB Bidan Rita Coblong',
                'alamat_praktik'   => 'Jl. Ir. H. Juanda No. 112, Lebakgede, Kec. Coblong, Kota Bandung',
                'kota'             => 'Bandung',
                'kecamatan'        => 'Coblong',
                'telepon'          => '081234567890',
                'spesialisasi'     => 'Asuhan Kehamilan & Persalinan Normal',
                'lat'              => -6.88985000,
                'lng'              => 107.61312000,
                'notes'            => 'Pemeriksaan kehamilan (ANC), cek Hb, dan detak jantung janin',
                'operating_hours'  => ['display' => 'Senin - Sabtu, 08.00 - 20.00 WIB (Persalinan 24 Jam)'],
            ],
            [
                'email'            => 'bidan.tati@prenava.com',
                'name'             => 'Bidan Hj. Tati Karwati, S.ST., Bdn.',
                'tempat_praktik'   => 'PMB Bidan Hj. Tati Karwati',
                'alamat_praktik'   => 'Jl. Buah Batu No. 184, Cijagra, Kec. Lengkong, Kota Bandung',
                'kota'             => 'Bandung',
                'kecamatan'        => 'Lengkong',
                'telepon'          => '081321987654',
                'spesialisasi'     => 'Kebidanan Komunitas & Konselor Laktasi',
                'lat'              => -6.93942000,
                'lng'              => 107.62561000,
                'notes'            => 'Periksa kehamilan, konseling laktasi, dan imunisasi dasar',
                'operating_hours'  => ['display' => 'Setiap Hari, 07.30 - 21.00 WIB (Persalinan 24 Jam)'],
            ],
            [
                'email'            => 'bidan.siti@prenava.com',
                'name'             => 'Bidan Siti Rahmawati, A.Md.Keb',
                'tempat_praktik'   => 'PMB Bidan Siti Rahmawati',
                'alamat_praktik'   => 'Jl. Dr. Djunjunan No. 105, Pajajaran, Kec. Cicendo, Kota Bandung',
                'kota'             => 'Bandung',
                'kecamatan'        => 'Cicendo',
                'telepon'          => '081298761234',
                'spesialisasi'     => 'Pemantauan Kehamilan & Senam Hamil',
                'lat'              => -6.89624000,
                'lng'              => 107.58845000,
                'notes'            => 'Konsultasi Buku KIA dan pemantauan Doppler detak jantung janin',
                'operating_hours'  => ['display' => 'Senin - Sabtu, 08.00 - 19.00 WIB'],
            ],
        ];

        foreach ($partnerBidans as $item) {
            $user = User::firstOrCreate(
                ['email' => $item['email']],
                [
                    'name'              => $item['name'],
                    'password'          => Hash::make('password123'),
                    'role'              => 'bidan',
                    'is_active'         => true,
                    'email_verified_at' => $now,
                ]
            );

            DB::table('bidan_profile')->updateOrInsert(
                ['user_id' => $user->user_id],
                [
                    'photo'                    => 'profiles/bidan/photo.jpg',
                    'tempat_praktik'           => $item['tempat_praktik'],
                    'alamat_praktik'           => $item['alamat_praktik'],
                    'kota_tempat_praktik'      => $item['kota'],
                    'kecamatan_tempat_praktik' => $item['kecamatan'],
                    'telepon_tempat_praktik'   => $item['telepon'],
                    'spesialisasi'             => $item['spesialisasi'],
                    'created_at'               => $now,
                    'updated_at'               => $now,
                ]
            );

            BidanLocation::updateOrCreate(
                [
                    'bidan_id'   => $user->user_id,
                    'is_primary' => true,
                ],
                [
                    'lat'             => $item['lat'],
                    'lng'             => $item['lng'],
                    'address_label'   => $item['tempat_praktik'] . ' (' . $item['alamat_praktik'] . ')',
                    'phone_override'  => $item['telepon'],
                    'notes'           => $item['notes'],
                    'operating_hours' => $item['operating_hours'],
                    'is_active'       => true,
                ]
            );
        }
    }
}
