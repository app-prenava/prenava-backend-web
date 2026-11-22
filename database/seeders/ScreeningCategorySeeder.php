<?php

namespace Database\Seeders;

use App\Models\ScreeningCategory;
use Illuminate\Database\Seeder;

class ScreeningCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'slug'          => 'low_literacy',
                'internal_name' => 'Literasi Kesehatan Maternal Rendah',
                'display_name'  => 'Panduan Dasar Kehamilan',
                'description'   => 'Kami menyiapkan panduan praktis seputar tanda bahaya, gizi dasar, serta fakta vs mitos kehamilan agar Bunda lebih yakin setiap hari.',
                'badge_color'   => '#FA6978',
                'recommended_features_json' => [
                    [
                        'feature_slug' => 'tips',
                        'title'        => 'Tips & Gizi',
                        'reason'       => 'Pelajari tanda bahaya dan panduan dasar kehamilan secara ringkas.',
                        'route'        => '/tips',
                        'priority'     => 1,
                    ],
                    [
                        'feature_slug' => 'local_wisdom',
                        'title'        => 'Kearifan Lokal',
                        'reason'       => 'Kenali mitos vs fakta medis seputar kehamilan agar lebih tenang.',
                        'route'        => 'action:local_wisdom',
                        'priority'     => 2,
                    ],
                    [
                        'feature_slug' => 'kalkulator_hpl',
                        'title'        => 'Kalkulator HPL',
                        'reason'       => 'Pantau usia kandungan dan perkembangan janin dari minggu ke minggu.',
                        'route'        => '/pregnancy-calculator',
                        'priority'     => 3,
                    ],
                    [
                        'feature_slug' => 'komunitas',
                        'title'        => 'Komunitas Bunda',
                        'reason'       => 'Tanya jawab dan berbagi pengalaman bersama sesama ibu hamil.',
                        'route'        => 'tab:community',
                        'priority'     => 4,
                    ],
                ],
            ],
            [
                'slug'          => 'nutrition_risk',
                'internal_name' => 'Risiko Gizi, Anemia & Stunting',
                'display_name'  => 'Fokus Gizi & Pencegahan Risiko',
                'description'   => 'Kami menonjolkan fitur pemantauan anemia, pencegahan stunting, hidrasi, dan olahraga aman untuk membantu menjaga stamina dan nutrisi Bunda serta janin.',
                'badge_color'   => '#E67E22',
                'recommended_features_json' => [
                    [
                        'feature_slug' => 'anemia',
                        'title'        => 'Prediksi Anemia',
                        'reason'       => 'Deteksi dini risiko anemia agar keluhan lemas/pusing segera tertangani.',
                        'route'        => '/deteksi-anemia',
                        'priority'     => 1,
                    ],
                    [
                        'feature_slug' => 'stunting',
                        'title'        => 'Risiko Stunting',
                        'reason'       => 'Cek risiko stunting dan dapatkan rekomendasi meal plan bergizi.',
                        'route'        => '/stunting/history',
                        'priority'     => 2,
                    ],
                    [
                        'feature_slug' => 'hidrasi',
                        'title'        => 'Rekomendasi Hidrasi',
                        'reason'       => 'Pantau target minum air putih harian agar tubuh tetap bugar.',
                        'route'        => '/hydration',
                        'priority'     => 3,
                    ],
                    [
                        'feature_slug' => 'olahraga',
                        'title'        => 'Rekomendasi Olahraga',
                        'reason'       => 'Latihan fisik ringan yang aman untuk menjaga kebugaran ibu hamil.',
                        'route'        => '/rekomendasi-olahraga',
                        'priority'     => 4,
                    ],
                ],
            ],
            [
                'slug'          => 'mental_support',
                'internal_name' => 'Kebutuhan Dukungan Kesehatan Mental',
                'display_name'  => 'Fokus Ketenangan & Kesehatan Mental',
                'description'   => 'Kesehatan emosional Bunda sama pentingnya dengan kesehatan fisik. Kami menonjolkan fitur pemantauan suasana hati, komunitas suportif, dan relaksasi.',
                'badge_color'   => '#8E44AD',
                'recommended_features_json' => [
                    [
                        'feature_slug' => 'depresi',
                        'title'        => 'Prediksi Depresi',
                        'reason'       => 'Pantau kondisi emosional dan risiko depresi lebih awal.',
                        'route'        => '/deteksi-depresi',
                        'priority'     => 1,
                    ],
                    [
                        'feature_slug' => 'komunitas',
                        'title'        => 'Komunitas Bunda',
                        'reason'       => 'Temukan ruang aman untuk bercerita dan saling menguatkan.',
                        'route'        => 'tab:community',
                        'priority'     => 2,
                    ],
                    [
                        'feature_slug' => 'olahraga',
                        'title'        => 'Rekomendasi Olahraga',
                        'reason'       => 'Gerakan relaksasi ringan bantu perbaiki suasana hati dan kualitas tidur.',
                        'route'        => '/rekomendasi-olahraga',
                        'priority'     => 3,
                    ],
                    [
                        'feature_slug' => 'tips',
                        'title'        => 'Tips & Gizi',
                        'reason'       => 'Baca panduan praktis mengelola kecemasan dan persiapan persalinan.',
                        'route'        => '/tips',
                        'priority'     => 4,
                    ],
                ],
            ],
            [
                'slug'          => 'anc_monitoring',
                'internal_name' => 'Kebutuhan Pendampingan Kontrol & Akses Bidan',
                'display_name'  => 'Fokus Kontrol Rutin & Bidan',
                'description'   => 'Agar pemeriksaan kehamilan lebih teratur, kami menonjolkan pencarian bidan terdekat, penjadwalan janji temu, dan pencatatan hasil kunjungan.',
                'badge_color'   => '#00897B',
                'recommended_features_json' => [
                    [
                        'feature_slug' => 'appointment',
                        'title'        => 'Janji Temu Bidan',
                        'reason'       => 'Atur jadwal konsultasi kehamilan ke bidan dengan lebih praktis.',
                        'route'        => '/appointments',
                        'priority'     => 1,
                    ],
                    [
                        'feature_slug' => 'kunjungan',
                        'title'        => 'Catatan Kunjungan',
                        'reason'       => 'Simpan riwayat hasil pemeriksaan ANC Bunda dalam satu tempat.',
                        'route'        => '/kunjungan',
                        'priority'     => 2,
                    ],
                    [
                        'feature_slug' => 'bidan_map',
                        'title'        => 'Peta Bidan',
                        'reason'       => 'Temukan lokasi bidan terpercaya di sekitar tempat tinggal Bunda.',
                        'route'        => '/bidan-map',
                        'priority'     => 3,
                    ],
                    [
                        'feature_slug' => 'kalkulator_hpl',
                        'title'        => 'Kalkulator HPL',
                        'reason'       => 'Ketahui usia kehamilan & jadwal pemeriksaan penting berikutnya.',
                        'route'        => '/pregnancy-calculator',
                        'priority'     => 4,
                    ],
                ],
            ],
            [
                'slug'          => 'balanced',
                'internal_name' => 'Kondisi & Literasi Seimbang / Mandiri',
                'display_name'  => 'Pemantauan Kehamilan Optimal',
                'description'   => 'Kesiapan Bunda sudah sangat baik! Kami menonjolkan fitur pemantauan preventif harian agar kesehatan Bunda dan janin tetap optimal hingga persalinan.',
                'badge_color'   => '#2E7D32',
                'recommended_features_json' => [
                    [
                        'feature_slug' => 'kalkulator_hpl',
                        'title'        => 'Kalkulator HPL',
                        'reason'       => 'Ikuti perkembangan janin dan hitung mundur menuju hari kelahiran.',
                        'route'        => '/pregnancy-calculator',
                        'priority'     => 1,
                    ],
                    [
                        'feature_slug' => 'hidrasi',
                        'title'        => 'Rekomendasi Hidrasi',
                        'reason'       => 'Pertahankan kebiasaan minum air putih yang sehat setiap hari.',
                        'route'        => '/hydration',
                        'priority'     => 2,
                    ],
                    [
                        'feature_slug' => 'olahraga',
                        'title'        => 'Rekomendasi Olahraga',
                        'reason'       => 'Jaga kebugaran tubuh secara konsisten sesuai trimester Bunda.',
                        'route'        => '/rekomendasi-olahraga',
                        'priority'     => 3,
                    ],
                    [
                        'feature_slug' => 'stunting',
                        'title'        => 'Risiko Stunting',
                        'reason'       => 'Optimalkan perencanaan gizi janin dengan fitur pencegahan stunting.',
                        'route'        => '/stunting/history',
                        'priority'     => 4,
                    ],
                ],
            ],
        ];

        foreach ($categories as $category) {
            ScreeningCategory::updateOrCreate(
                ['slug' => $category['slug']],
                $category
            );
        }
    }
}
