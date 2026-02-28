<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ShopSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $products = [
            [
                'product_name' => 'Pregnancy Pillow - Bantal Hamil U-Shape',
                'category' => 'Bantal Hamil',
                'description' => 'Bantal hamil berbentuk U yang nyaman untuk mendukung perut, punggung, dan lutut saat tidur. Terbuat dari kapas premium yang lembut dan breathable. Membantu mengurangi sakit punggung dan memberikan kualitas tidur yang lebih baik selama kehamilan.',
                'price' => '250000',
                'photo' => 'shop/pregnancy-pillow.png',
                'url' => 'https://shope.ee/pregnancy-pillow-u-shape-original'
            ],
            [
                'product_name' => 'Stretch Mark Cream - Bio Oil',
                'category' => 'Perawatan Kulit',
                'description' => 'Minyak khusus untuk mencegah dan mengurangi stretch mark selama kehamilan. Mengandung Vitamin A, E, dan minyak alami yang membantu meregenerasi kulit. Aman untuk ibu hamil dan telah teruji klinis.',
                'price' => '180000',
                'photo' => 'shop/stretch-mark-cream.png',
                'url' => 'https://shope.ee/bio-oil-stretch-mark-cream-original'
            ],
            [
                'product_name' => 'Maternity Dress - Gaun Hamil Nyaman',
                'category' => 'Pakaian Hamil',
                'description' => 'Gaun hamil dengan bahan katun stretch yang lembut dan nyaman. Desain yang fashionable dan bisa dipakai hingga 9 bulan kehamilan. Cocok untuk acara santai maupun formal.',
                'price' => '195000',
                'photo' => 'shop/maternity-dress.png',
                'url' => 'https://shope.ee/maternity-dress-comfy-wear'
            ],
            [
                'product_name' => 'Prenatal Vitamins - Suplemen Kehamilan',
                'category' => 'Suplemen',
                'description' => 'Suplemen kehamilan lengkap dengan DHA, asam folat, zat besi, dan kalsium. Mendukung perkembangan janin dan kesehatan ibu hamil. Terdaftar BPOM dan direkomendasikan dokter kandungan.',
                'price' => '320000',
                'photo' => 'shop/prenatal-vitamins.png',
                'url' => 'https://shope.ee/prenatal-vitamins-dha-folic-acid'
            ],
            [
                'product_name' => 'Pregnancy Belly Band - Korset Penyangga Perut',
                'category' => 'Korset Hamil',
                'description' => 'Korset penyangga perut untuk mengurangi beban pada punggung dan pinggang. Bahan breathable dan elastis yang nyaman dipakai sehari-hari. Membantu postur tubuh tetap baik selama kehamilan.',
                'price' => '150000',
                'photo' => 'shop/belly-band.png',
                'url' => 'https://shope.ee/pregnancy-belly-band-support'
            ],
            [
                'product_name' => 'Breast Pump - Alat ASI Manual',
                'category' => 'ASI & Menyusui',
                'description' => 'Alat pompa ASI manual yang ergonomis dan mudah digunakan. Terbuat dari bahan BPA-free yang aman untuk bayi. Dilengkapi dengan botol penyimpan dan tutup anti tumpah.',
                'price' => '275000',
                'photo' => 'shop/breast-pump.png',
                'url' => 'https://shope.ee/manual-breast-pump-bpa-free'
            ],
            [
                'product_name' => 'Maternity Leggings - Celana Hamil Stretch',
                'category' => 'Pakaian Hamil',
                'description' => 'Celana leggings hamil dengan bahan super stretch yang nyaman. Dapat dipakai dari trimester pertama hingga postpartum. Pinggang elastis yang tidak menekan perut.',
                'price' => '125000',
                'photo' => 'shop/maternity-leggings.png',
                'url' => 'https://shope.ee/maternity-leggings-stretch-comfort'
            ],
            [
                'product_name' => 'Pregnancy Test Pack - Alat Tes Kehamilan',
                'category' => 'Alat Kesehatan',
                'description' => 'Test pack kehamilan dengan akurasi tinggi 99%. Hasil dapat dibaca dalam 3 menit. Mudah digunakan dan praktis. Mendapatkan 2 strip dalam satu kemasan.',
                'price' => '45000',
                'photo' => 'shop/test-pack.png',
                'url' => 'https://shope.ee/pregnancy-test-pack-accurate'
            ],
            [
                'product_name' => 'Baby Doppler - Alat Dengar Jantung Janin',
                'category' => 'Alat Kesehatan',
                'description' => 'Alat untuk mendengar detak jantung janin sejak usia 12 minggu. Dilengkapi layar LCD untuk menampilkan detak jantung per menit. Aman dan mudah digunakan di rumah.',
                'price' => '450000',
                'photo' => 'shop/baby-doppler.png',
                'url' => 'https://shope.ee/baby-doppler-fetal-heartbeat-monitor'
            ],
            [
                'product_name' => 'Postpartum Recovery Belt - Sabuk Pasca Melahirkan',
                'category' => 'Pasca Melahirkan',
                'description' => 'Sabuk pemulihan pasca melahirkan untuk membantu mengembalikan bentuk perut. Bahan breathable dan dapat disesuaikan ukurannya. Membantu mengurangi bengkak dan nyeri pasca persalinan.',
                'price' => '175000',
                'photo' => 'shop/recovery-belt.png',
                'url' => 'https://shope.ee/postpartum-recovery-belt-binder'
            ],
            [
                'product_name' => 'Nursing Bra - Bra Menyusui Nyaman',
                'category' => 'ASI & Menyusui',
                'description' => 'Bra menyusui dengan kancing depan yang praktis. Bahan katun yang lembut dan menyerap keringat. Dilengkapi kawat penyangga yang nyaman. Tersedia ukuran S-XXL.',
                'price' => '135000',
                'photo' => 'shop/nursing-bra.png',
                'url' => 'https://shope.ee/nursing-bra-breastfeeding-comfort'
            ],
            [
                'product_name' => 'Pregnancy Journal - Buku Catatan Kehamilan',
                'category' => 'Buku & Jurnal',
                'description' => 'Buku jurnal kehamilan untuk mencatat setiap momen berharga. Dilengkapi dengan tempat foto, checklist kesehatan, dan tips kehamilan. Desain cantik dan inspiratif.',
                'price' => '95000',
                'photo' => 'shop/pregnancy-journal.png',
                'url' => 'https://shope.ee/pregnancy-journal-notebook-memory'
            ],
            [
                'product_name' => 'Maternity Support Belt - Sabuk Penyangga Hamil',
                'category' => 'Korset Hamil',
                'description' => 'Sabuk penyangga kehamilan yang mengurangi tekanan pada punggung dan pinggul. Bahan elastis yang tidak panas. Dapat disesuaikan dengan pertumbuhan perut.',
                'price' => '165000',
                'photo' => 'shop/support-belt.png',
                'url' => 'https://shope.ee/maternity-pregnancy-support-belt'
            ],
            [
                'product_name' => 'Ginger Candy - Permen Jahe Untuk Mual',
                'category' => 'Makanan & Minuman',
                'description' => 'Permen jahe alami untuk mengurangi morning sickness. Terbuat dari jahe pilihan dan gula aren. Praktis dibawa kemana saja dan aman untuk ibu hamil.',
                'price' => '35000',
                'photo' => 'shop/ginger-candy.png',
                'url' => 'https://shope.ee/ginger-candy-morning-sickness-relief'
            ],
            [
                'product_name' => 'Baby Carrier - Gendongan Bayi Hipseat',
                'category' => 'Gendongan Bayi',
                'description' => 'Gendongan bayi dengan posisi hips yang sehat untuk pinggang bayi. Bahan katun yang adem dan kuat. Terdapat penyangga kepala untuk newborn. Aman dan ergonomis.',
                'price' => '285000',
                'photo' => 'shop/baby-carrier.png',
                'url' => 'https://shope.ee/baby-carrier-hipseat-ergonomic-safe'
            ],
            [
                'product_name' => 'Pregnancy Body Lotion - Lotion Badan Ibu Hamil',
                'category' => 'Perawatan Kulit',
                'description' => 'Lotion khusus ibu hamil dengan vitamin E dan shea butter. Melembapkan kulit yang kering dan membantu mencegah stretch mark. Wangi lembut dan tidak menyengat.',
                'price' => '85000',
                'photo' => 'shop/body-lotion.png',
                'url' => 'https://shope.ee/pregnancy-body-lotion-vitamin-e'
            ],
            [
                'product_name' => 'Maternity Nursing Pillow - Bantal Menyusui',
                'category' => 'Bantal Menyusui',
                'description' => 'Bantal menyusui berbentuk U yang memudahkan posisi menyusui. Isi dakron premium yang empuk dan tidak mudah kempes. Sarung dapat dicuci dan diganti.',
                'price' => '195000',
                'photo' => 'shop/nursing-pillow.png',
                'url' => 'https://shope.ee/nursing-pillow-breastfeeding-u-shape'
            ],
            [
                'product_name' => 'Prenatal Yoga Mat - Matras Yoga Hamil',
                'category' => 'Olahraga',
                'description' => 'Matras yoga dengan ketebalan 10mm yang nyaman untuk ibu hamil. Anti-slip dan mudah dibawa-bawa. Cocok untuk senam hamil, yoga, dan pilates.',
                'price' => '145000',
                'photo' => 'shop/yoga-mat.png',
                'url' => 'https://shope.ee/prenatal-yoga-mat-exercise-thick'
            ],
            [
                'product_name' => 'Baby Movement Tracker - Alat Monitor Gerakan Janin',
                'category' => 'Alat Kesehatan',
                'description' => 'Alat untuk memantau gerakan janin secara teratur. Dilengkapi aplikasi untuk mencatat dan menganalisis pola gerakan. Membantu mendeteksi ketidaknormalan sejak dini.',
                'price' => '350000',
                'photo' => 'shop/movement-tracker.png',
                'url' => 'https://shope.ee/baby-movement-tracker-kick-counter'
            ],
            [
                'product_name' => 'Compression Socks - Kaos Kaki Kompresi Hamil',
                'category' => 'Pakaian Hamil',
                'description' => 'Kaos kaki kompresi untuk mengurangi bengkak pada kaki dan tungkai. Meningkatkan sirkulasi darah dan mencegah varises. Bahan breathable dan nyaman dipakai seharian.',
                'price' => '110000',
                'photo' => 'shop/compression-socks.png',
                'url' => 'https://shope.ee/compression-socks-pregnancy-swelling'
            ]
        ];

        // Hapus data shop yang lama
        DB::table('shop')->delete();

        // Ambil user pertama sebagai seller (bisa admin atau user lain)
        $sellerId = DB::table('users')->where('role', 'admin')->value('user_id');

        if (!$sellerId) {
            $this->command->warn('No admin user found. Using first available user as seller.');
            $sellerId = DB::table('users')->first()->user_id;
        }

        // Ensure shop directory exists
        Storage::disk('public')->makeDirectory('shop');

        foreach ($products as $product) {
            // Generate a placeholder image if the file doesn't exist
            $photoPath = $product['photo']; // e.g. "shop/nursing-bra.png"
            if (!Storage::disk('public')->exists($photoPath)) {
                $this->generatePlaceholderImage($photoPath, $product['product_name']);
            }

            DB::table('shop')->insert([
                'user_id' => $sellerId,
                'product_name' => $product['product_name'],
                'category' => $product['category'],
                'description' => $product['description'],
                'price' => $product['price'],
                'photo' => $product['photo'],
                'url' => $product['url'],
                'average_rating' => 0,
                'rating_count' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $this->command->info("Successfully created " . count($products) . " shop products with matching image files.");
    }

    private function generatePlaceholderImage(string $path, string $label): void
    {
        $width = 400;
        $height = 400;
        $img = imagecreatetruecolor($width, $height);

        // Background color (soft pink to match app theme)
        $bg = imagecolorallocate($img, 250, 105, 120);
        imagefill($img, 0, 0, $bg);

        // Text color
        $white = imagecolorallocate($img, 255, 255, 255);

        // Word-wrap the label
        $fontSize = 4; // built-in font size 1-5
        $maxCharsPerLine = 20;
        $lines = explode("\n", wordwrap($label, $maxCharsPerLine, "\n", true));

        $lineHeight = imagefontheight($fontSize) + 4;
        $totalHeight = count($lines) * $lineHeight;
        $startY = ($height - $totalHeight) / 2;

        foreach ($lines as $i => $line) {
            $textWidth = imagefontwidth($fontSize) * strlen($line);
            $x = ($width - $textWidth) / 2;
            $y = (int)($startY + $i * $lineHeight);
            imagestring($img, $fontSize, (int)$x, $y, $line, $white);
        }

        // Save as PNG
        ob_start();
        imagepng($img);
        $pngData = ob_get_clean();
        imagedestroy($img);

        Storage::disk('public')->put($path, $pngData);
    }
}
