<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\LocalWisdom;

class LocalWisdomSeeder extends Seeder
{
    public function run(): void
    {
        $myths = [
            // ── Nasional ──────────────────────────────────────────────────
            [
                'myth' => 'Duduk di Tengah Pintu',
                'reason' => 'Posisi ambang pintu menciptakan aliran udara kencang (angin duduk). Ibu hamil lebih rentan masuk angin yang bisa mengganggu sirkulasi oksigen ke janin.',
                'region' => 'Nasional',
            ],
            [
                'myth' => 'Makan Nanas Muda',
                'reason' => 'Nanas mengandung bromelain tinggi yang bisa melunakkan leher rahim dan memicu kontraksi dini atau keguguran pada trimester awal.',
                'region' => 'Nasional',
            ],
            [
                'myth' => 'Mandi Terlalu Malam',
                'reason' => 'Suhu malam yang dingin meningkatkan risiko kram otot dan gangguan pernapasan. Tubuh ibu hamil membutuhkan suhu yang stabil untuk metabolisme janin.',
                'region' => 'Nasional',
            ],

            // ── Jawa ──────────────────────────────────────────────────────
            [
                'myth' => 'Melilitkan Handuk di Leher',
                'reason' => 'Handuk di leher membuat ibu merasa gerah dan sesak. Ini pengingat agar ibu menjaga postur tubuh tetap tegak dan tidak membungkuk berlebihan.',
                'region' => 'Jawa',
            ],
            [
                'myth' => 'Suami Dilarang Membunuh Binatang',
                'reason' => 'Menjaga empati dan ketenangan batin. Jika ibu melihat hal sadis, hormon stres (kortisol) meningkat yang buruk bagi saraf janin.',
                'region' => 'Jawa',
            ],
            [
                'myth' => 'Menjahit atau Menambal',
                'reason' => 'Menjahit lama membuat mata lelah dan punggung pegal karena posisi statis. Ibu hamil butuh relaksasi otot punggung secara berkala.',
                'region' => 'Jawa',
            ],
            [
                'myth' => 'Tidur Siang Terlalu Lama',
                'reason' => 'Tidur siang berlebihan bikin badan lemas dan sulit tidur malam. Kualitas tidur malam yang baik sangat penting untuk regenerasi sel janin.',
                'region' => 'Jawa',
            ],

            // ── Sunda ─────────────────────────────────────────────────────
            [
                'myth' => 'Membawa Bangle / Panglai',
                'reason' => 'Aroma rimpang Bangle berfungsi sebagai aromaterapi alami yang efektif mengurangi rasa mual (morning sickness) dan menenangkan sistem saraf.',
                'region' => 'Sunda',
            ],
            [
                'myth' => 'Makan di Piring Besar',
                'reason' => 'Psikologi piring besar membuat ibu merasa porsinya sedikit sehingga tidak makan berlebihan. Obesitas saat hamil meningkatkan risiko preeklampsia.',
                'region' => 'Sunda',
            ],
            [
                'myth' => 'Mengikat Rambut Terlalu Kencang',
                'reason' => 'Perubahan hormon saat hamil membuat rambut lebih rapuh. Mengikat terlalu kencang memicu sakit kepala dan kerontokan rambut yang parah.',
                'region' => 'Sunda',
            ],

            // ── Bali ──────────────────────────────────────────────────────
            [
                'myth' => 'Makan Makanan yang Terlalu Pedas',
                'reason' => 'Lambung ibu hamil lebih sensitif. Makanan sangat pedas memicu asam lambung naik (heartburn) yang sangat tidak nyaman bagi ibu.',
                'region' => 'Bali',
            ],
            [
                'myth' => 'Suami Merenovasi Rumah',
                'reason' => 'Menghindarkan ibu dari paparan debu, bau cat (VOC), dan kebisingan yang bisa memicu stres dan gangguan pernapasan pada janin.',
                'region' => 'Bali',
            ],
            [
                'myth' => 'Keluar Saat Matahari Terbenam',
                'reason' => 'Waktu pergantian cahaya membuat penglihatan kurang fokus. Risiko terjatuh atau tersandung lebih tinggi bagi ibu hamil yang keseimbangannya berubah.',
                'region' => 'Bali',
            ],

            // ── Sumatera / Melayu ──────────────────────────────────────────
            [
                'myth' => 'Membawa Gunting atau Peniti',
                'reason' => 'Berfungsi sebagai perlindungan mental (efek plasebo). Rasa aman dan berani membuat ibu lebih rileks saat harus keluar rumah.',
                'region' => 'Sumatera',
            ],
            [
                'myth' => 'Makan Pisang Sungsang',
                'reason' => 'Mengajarkan etika dan keteraturan saat makan. Pola makan yang teratur membantu metabolisme tubuh ibu hamil tetap stabil.',
                'region' => 'Sumatera',
            ],
            [
                'myth' => 'Membatin Keburukan Orang Lain',
                'reason' => 'Pikiran negatif terus-menerus memicu stres psikis. Kestabilan emosi ibu sangat berpengaruh pada temperamen bayi setelah lahir nanti.',
                'region' => 'Sumatera',
            ],

            // ── Bugis / Makassar ──────────────────────────────────────────
            [
                'myth' => 'Minum Sambil Berdiri',
                'reason' => 'Minum sambil duduk membantu ginjal menyaring air lebih efektif. Beban ginjal ibu hamil lebih berat karena menyaring limbah dari janin juga.',
                'region' => 'Bugis',
            ],
            [
                'myth' => 'Mencela Fisik Orang Lain',
                'reason' => 'Mengajarkan ibu menjaga lisan dan pikiran positif. Afirmasi positif setiap hari membantu kesehatan mental ibu tetap terjaga.',
                'region' => 'Bugis',
            ],
            [
                'myth' => 'Tidur Menghadap Kanan Terus',
                'reason' => 'Disarankan bergantian posisi. Namun posisi miring kiri lebih baik untuk aliran nutrisi dari plasenta ke janin.',
                'region' => 'Bugis',
            ],
        ];

        foreach ($myths as $myth) {
            LocalWisdom::updateOrCreate(['myth' => $myth['myth']], $myth);
        }
    }
}
