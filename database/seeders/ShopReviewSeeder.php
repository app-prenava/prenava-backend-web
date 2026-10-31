<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ShopReviewSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        // Ambil semua user dengan role ibu_hamil
        $ibuHamilUsers = DB::table('users')->where('role', 'ibu_hamil')->pluck('user_id')->toArray();

        if (empty($ibuHamilUsers)) {
            $this->command->warn('No ibu_hamil users found. Please run IbuHamilSeeder first.');
            return;
        }

        // Ambil semua produk dari shop
        $products = DB::table('shop')->pluck('product_id')->toArray();

        if (empty($products)) {
            $this->command->warn('No shop products found. Please run ShopSeeder first.');
            return;
        }

        // Komentar review berdasarkan rating
        $reviewComments = [
            5 => [
                'Sangat puas! Produknya berkualitas dan sesuai deskripsi. Pengiriman cepat dan包装 aman.',
                'Barang bagus banget! Saya beli untuk kehamilan dan sangat membantu. Recommended!',
                'Love it! Kualitas premium, harga terjangkau. Pasti akan beli lagi.',
                'Produknya juara! Saya repurchase berkali-kali. Seller juga ramah dan responsive.',
                'Worth every penny! Kualitas di atas harga. Sangat puas dengan pembelian ini.',
                'Terbaik! Produk original, pengiriman super cepat. Packing rapi dan aman.',
                'Suka banget! Kualitas tidak mengecewakan. Pasti repeat order.',
                'Perfect! Sesuai ekspektasi, bahan bagus, ukuran pas. Seller terpercaya.',
                'Mantap! Barang asli, pengiriman cepat, harga bersaing. Sukses terus seller!',
                'Highly recommended! Produk kebutuhan hamil yang sangat membantu. Quality is top notch!'
            ],
            4 => [
                'Bagus, tapi pengiriman agak lama. Tapi produknya oke.',
                'Kualitas baik, hanya saja packaging bisa lebih baik lagi.',
                'Sesuai harga, lumayan bagus. Ada sedikit minus di bagian jahitan.',
                'Overall puas, hanya warnanya sedikit beda dari foto. Tapi tetap oke.',
                'Produk ok, tapi kurang bubble wrap tebal. Alhamdulillah sampai dengan selamat.',
                'Barangnya bagus, ukuran pas bahan. Recommended seller!',
                'Cukup puas, tapi bisa lebih baik lagi di kualitas jahitan.',
                'Oke lah untuk harga segini. Fungsi sesuai.',
                'Produk standar, tidak terlalu bagus tapi tidak buruk juga.',
                'Puas secara keseluruhan, hanya perlu sedikit improvement.'
            ],
            3 => [
                'Biasa saja, kualitas standar. Sesuai dengan harganya.',
                ' Produknya ok, tapi agak mengecewakan di beberapa bagian.',
                'Cukuplah, tapi mungkin tidak akan repurchase.',
                'Standar quality, ada plus minusnya.',
                'Biasa-biasa saja, tidak ada yang istimewa.',
                'Okelah, tapi ekspektasi saya lebih tinggi.',
                'Produk biasa, bisa dicoba untuk yang sekali pakai.',
                'Passable, tapi tidak terlalu recommended.',
                'So-so quality, you get what you pay for.',
                'Average product, nothing special.'
            ],
            2 => [
                'Agak mengecewakan, kualitas di bawah ekspektasi.',
                'Bahan kurang bagus, jahitan agak lepas.',
                'Kurang puas, produk tidak sesuai deskripsi.',
                'Harga cukup mahal untuk kualitas seperti ini.',
                'Produk biasa saja, tapi pengiriman sangat lama.',
                'Saya kecewa, ada beberapa cacat di produk.',
                'Tidak terlalu recommended, kualitas pas-pasan.',
                'Below average, mungkin tidak akan beli lagi.',
                'Kualitas kurang, tapi masih bisa digunakan.',
                'Menjengkelkan, produk tidak seperti di foto.'
            ],
            1 => [
                'Sangat kecewa! Produk jelek sekali, tidak sesuai deskripsi.',
                'Jangan beli! Kualitas buruk, uang saya terbuang sia-sia.',
                'Worst purchase ever! Barang rusak saat diterima.',
                'Seller tidak responsif, produk tidak original.',
                'Penipu! Barang beda jauh sama foto. Tolong ditindak.',
                'Menyesal banget beli disini. Kualitas sampah!',
                'Horrible! Tidak pernah lagi belanja disini.',
                'Disappointing! Bahan murahan, ukuran tidak sesuai.',
                'Terburuk! Pengiriman lama, barang jelek, seller cuek.',
                'Regret buying this! Total waste of money.'
            ]
        ];

        $totalReviews = 0;

        // Untuk setiap produk, buat 5 review dari user berbeda
        foreach ($products as $productId) {
            // Pilih 5 user berbeda secara acak
            $selectedUsers = array_rand($ibuHamilUsers, 5);
            shuffle($selectedUsers); // Acak lagi agar tidak urut

            foreach ($selectedUsers as $userIndex) {
                $userId = $ibuHamilUsers[$userIndex];

                // Generate rating dengan distribusi:
                // 40% rating 5
                // 30% rating 4
                // 15% rating 3
                // 10% rating 2
                // 5% rating 1
                $rand = rand(1, 100);
                if ($rand <= 40) {
                    $rating = 5;
                } elseif ($rand <= 70) {
                    $rating = 4;
                } elseif ($rand <= 85) {
                    $rating = 3;
                } elseif ($rand <= 95) {
                    $rating = 2;
                } else {
                    $rating = 1;
                }

                // Pilih komentar berdasarkan rating
                $comments = $reviewComments[$rating];
                $comment = $comments[array_rand($comments)];

                // Generate tanggal review random dalam 3 bulan terakhir
                $daysAgo = rand(1, 90);
                $tanggalReview = date('Y-m-d H:i:s', strtotime("-{$daysAgo} days"));

                // Insert review
                DB::table('shop_reviews')->insert([
                    'product_id' => $productId,
                    'user_id' => $userId,
                    'rating' => $rating,
                    'comment' => $comment,
                    'created_at' => $tanggalReview,
                    'updated_at' => $tanggalReview,
                ]);

                $totalReviews++;
            }

            // Update average_rating dan rating_count di tabel shop
            $reviews = DB::table('shop_reviews')
                ->where('product_id', $productId)
                ->select(DB::raw('AVG(rating) as avg_rating, COUNT(*) as count'))
                ->first();

            DB::table('shop')
                ->where('product_id', $productId)
                ->update([
                    'average_rating' => number_format($reviews->avg_rating, 2),
                    'rating_count' => $reviews->count,
                    'updated_at' => $now,
                ]);
        }

        $this->command->info("Successfully created {$totalReviews} shop reviews for " . count($products) . " products.");
    }
}
