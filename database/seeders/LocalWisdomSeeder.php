<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\LocalWisdom;

class LocalWisdomSeeder extends Seeder
{
    public function run(): void
    {
        $myths = [
            [
                'myth' => 'Duduk di Tengah Pintu',
                'reason' => 'Posisi ambang pintu menciptakan aliran udara kencang dari dua sisi. Ini berisiko menyebabkan masuk angin pada ibu hamil yang lebih rentan terhadap perubahan suhu.',
                'region' => 'Umum / Nasional',
            ],
            [
                'myth' => 'Melilitkan Handuk di Leher',
                'reason' => 'Handuk di leher mencegah ibu merasa gerah dan sesak napas. Mitos ini juga dikaitkan sebagai pengingat agar ibu tidak membungkuk berlebihan saat bekerja.',
                'region' => 'Jawa / Sunda',
            ],
            [
                'myth' => 'Membawa Gunting atau Peniti',
                'reason' => 'Berfungsi sebagai alat perlindungan mental (plasebo) agar ibu merasa lebih aman dan tenang saat bepergian sendirian — mengurangi kecemasan yang buruk bagi janin.',
                'region' => 'Melayu / Sumatera',
            ],
            [
                'myth' => 'Makan Nanas Muda',
                'reason' => 'Nanas mengandung enzim bromelain yang terbukti secara ilmiah dapat memicu kontraksi rahim dan meningkatkan risiko keguguran, terutama pada trimester pertama.',
                'region' => 'Umum / Nasional',
            ],
            [
                'myth' => 'Suami Dilarang Membunuh Binatang',
                'reason' => 'Bertujuan menjaga empati dan ketenangan batin suami. Ibu yang merasa aman dan dilindungi akan memiliki hormon stres yang lebih rendah, berdampak positif pada janin.',
                'region' => 'Jawa / Bali',
            ],
            [
                'myth' => 'Makan di Piring Besar',
                'reason' => 'Secara psikologis, piring besar menciptakan ilusi porsi yang lebih sedikit sehingga ibu tidak makan berlebihan. Berat badan berlebih mempersulit proses persalinan.',
                'region' => 'Bugis / Makassar',
            ],
            [
                'myth' => 'Keluar Rumah Saat Maghrib',
                'reason' => 'Waktu maghrib identik dengan pencahayaan yang minim (remang-remang). Risiko ibu hamil terjatuh, tersandung, atau mengalami kecelakaan kecil jauh lebih tinggi.',
                'region' => 'Hampir Seluruh Daerah',
            ],
            [
                'myth' => 'Mencela Fisik Orang Lain',
                'reason' => 'Mengajarkan ibu menjaga lisan dan berpikiran positif. Pikiran negatif memicu hormon kortisol yang mengganggu perkembangan janin dan kesehatan mental ibu.',
                'region' => 'Jawa / Kalimantan',
            ],
            [
                'myth' => 'Makan Pisang dari Ujung Bawah',
                'reason' => 'Larangan ini mengajarkan keteraturan dan etika saat makan. Makan dengan teratur dan tidak terburu-buru penting untuk pencernaan ibu hamil yang lebih sensitif.',
                'region' => 'Sumatera / Melayu',
            ],
            [
                'myth' => 'Tidur Siang Terlalu Lama',
                'reason' => 'Tidur siang berlebihan mengganggu ritme sirkadian. Ibu yang aktif bergerak ringan di siang hari memiliki kualitas tidur malam yang lebih baik dan tidak mudah lemas.',
                'region' => 'Sunda / Jawa',
            ],
            [
                'myth' => 'Suami Renovasi Rumah Saat Hamil',
                'reason' => 'Debu konstruksi mengandung partikel berbahaya, dan bau cat mengandung senyawa VOC (Volatile Organic Compounds) yang terbukti berbahaya bagi perkembangan janin.',
                'region' => 'Jawa / Bali',
            ],
            [
                'myth' => 'Membawa Bangle atau Panglai',
                'reason' => 'Aroma rimpang bangle berfungsi sebagai aromaterapi alami. Penelitian menunjukkan aromaterapi tertentu efektif menenangkan sistem saraf dan mengurangi mual pada ibu hamil.',
                'region' => 'Sunda',
            ],
            [
                'myth' => 'Menjahit atau Menambal',
                'reason' => 'Menjahit dalam waktu lama membuat ibu membungkuk dan menatap fokus. Ini menyebabkan nyeri punggung, mata cepat lelah, dan postur buruk yang berbahaya saat hamil.',
                'region' => 'Betawi / Jawa',
            ],
            [
                'myth' => 'Mandi Terlalu Malam',
                'reason' => 'Suhu malam yang dingin berisiko menyebabkan kram otot dan hipotermia ringan. Sistem imun ibu hamil lebih rentan, sehingga paparan suhu ekstrem perlu dihindari.',
                'region' => 'Umum / Nasional',
            ],
            [
                'myth' => 'Membatin Keburukan Orang Lain',
                'reason' => 'Pikiran negatif berkepanjangan meningkatkan hormon kortisol (hormon stres). Kadar kortisol tinggi pada ibu hamil berdampak langsung pada perkembangan otak janin.',
                'region' => 'Jawa / Minang',
            ],
        ];

        foreach ($myths as $myth) {
            LocalWisdom::updateOrCreate(['myth' => $myth['myth']], $myth);
        }
    }
}
