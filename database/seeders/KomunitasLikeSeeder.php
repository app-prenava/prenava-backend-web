<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class KomunitasLikeSeeder extends Seeder
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

        // Ambil semua post komunitas
        $komunitasPosts = DB::table('komunitas')->pluck('post_id')->toArray();

        if (empty($komunitasPosts)) {
            $this->command->warn('No komunitas posts found. Please run KomunitasPostSeeder first.');
            return;
        }

        $totalLikes = 0;
        $likesPerPost = [];

        // Untuk setiap post, berikan like dari user lain (bukan owner post)
        foreach ($komunitasPosts as $postId) {
            // Ambil owner post
            $post = DB::table('komunitas')->where('post_id', $postId)->first();
            $ownerId = $post->user_id;

            // Tentukan jumlah like random antara 5-15 like per post
            $numLikes = rand(5, 15);

            // Ambil user yang bukan owner
            $potentialLikers = array_filter($ibuHamilUsers, function($userId) use ($ownerId) {
                return $userId != $ownerId;
            });

            // Jika user yang available kurang dari numLikes, ambil semua
            if (count($potentialLikers) < $numLikes) {
                $numLikes = count($potentialLikers);
            }

            // Pilih user secara acak untuk memberikan like
            if (count($potentialLikers) > 0) {
                $selectedLikers = array_rand($potentialLikers, min($numLikes, count($potentialLikers)));

                foreach ((array)$selectedLikers as $key) {
                    $likerId = $potentialLikers[$key];

                    // Generate tanggal like random dalam 30 hari terakhir
                    $daysAgo = rand(1, 30);
                    $tanggalLike = date('Y-m-d H:i:s', strtotime("-{$daysAgo} days"));

                    // Insert like
                    DB::table('like')->insert([
                        'post_id' => $postId,
                        'user_id' => (string)$likerId,
                        'created_at' => $tanggalLike,
                        'updated_at' => $tanggalLike,
                    ]);

                    $totalLikes++;
                }

                $likesPerPost[$postId] = $numLikes;
            }
        }

        // Update apresiasi count di tabel komunitas
        foreach ($likesPerPost as $postId => $numLikes) {
            DB::table('komunitas')
                ->where('post_id', $postId)
                ->update([
                    'apresiasi' => $numLikes,
                    'updated_at' => $now,
                ]);
        }

        $this->command->info("Successfully created {$totalLikes} likes for " . count($komunitasPosts) . " komunitas posts.");
    }
}
