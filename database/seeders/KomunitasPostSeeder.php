<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class KomunitasPostSeeder extends Seeder
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

        // Kategori postingan
        $categories = ['Keluhan Kehamilan', 'Tips Kehamilan', 'Kegiatan Kehamilan', 'Obat-obatan Kehamilan', 'Rekomendasi Kehamilan'];

        // Data judul dan konten postingan berdasarkan kategori
        $postsData = [
            'Keluhan Kehamilan' => [
                ['judul' => 'Morning sickness parah', 'deskripsi' => 'Morning sickness saya parah banget ya bun, setiap pagi muntah terus. Ada yang punya tips nggak sih biar nggak mual-mual terus?'],
                ['judul' => 'Sakit perut bawah', 'deskripsi' => 'Bun, perut bawah saya sering sakit ya. Apa ini normal ya? Kadang sakitnya sampai ke pinggang.'],
                ['judul' => 'Pegal pinggang dan susah tidur', 'deskripsi' => 'Saya sering banget pegal pinggang dan susah tidur malam. Katanya normal di trimester ketiga, tapi kok ya capek banget.'],
                ['judul' => 'Kaki bengkak', 'deskripsi' => 'Bengkak di kaki dan tangan saya mulai kelihatan nih bun. Apa ada cara buat ngurangin bengkak?'],
                ['judul' => 'Sensitive dan gampang nangis', 'deskripsi' => 'Saya jadi sensitif banget dan gampang nangis sekarang. Ada yang ngalamin juga nggak sih?'],
                ['judul' => 'Nafsu makan hilang', 'deskripsi' => 'Nafsu makan saya hilang total bun. Padahal harus makan buat nutrisi bayi. Ada saran makanan yang enak tapi bergizi?'],
                ['judul' => 'Sesak napas', 'deskripsi' => 'Saya sering sesak napas apalagi kalau mau tidur. Ini normal nggak ya bun di usia kehamilan 7 bulan?'],
                ['judul' => 'Panas dingin dan keringat malam', 'deskripsi' => 'Bun, saya sering merasa panas dingin dan berkeringat malam hari. Apa ini tanda apa ya?'],
                ['judul' => 'Sering buang air kecil', 'deskripsi' => 'Saya jadi sering buang air kecil terutama malam hari. Tidur jadi terganggu terus.'],
                ['judul' => 'Kaki kram malam hari', 'deskripsi' => 'Kaki saya sering kram di tengah malam bun. Sakit banget sampai bangun tidur. Ada solusi nggak?'],
            ],
            'Tips Kehamilan' => [
                ['judul' => 'Minum air putih', 'deskripsi' => 'Share tips ya bun, minum air putih yang cukup itu beneran penting. Kulit jadi lebih sehat dan bayi juga sehat.'],
                ['judul' => 'Jalan kaki pagi', 'deskripsi' => 'Buat bumil yang suka olahraga, jalan kaki pagi itu sangat recommended. Sehat buat ibu dan janin.'],
                ['judul' => 'Atasi morning sickness', 'deskripsi' => 'Tips dari saya, selalu bawa biskuit atau kerupuk buat atasi morning sickness. Lumayan ngebantu.'],
                ['judul' => 'Rutin checkup', 'deskripsi' => 'Jangan lupa periksa ke bidan/dokter secara teratur ya bun. Monitoring perkembangan bayi itu penting.'],
                ['judul' => 'Yoga hamil', 'deskripsi' => 'Yoga hamil itu beneran bantu banget buat relaksasi dan persiapan persalinan. Coba deh bun.'],
                ['judul' => 'Cari dokter yang cocok', 'deskripsi' => 'Tips cari dokter kandungan yang cocok, jangan ragu ganti kalau merasa nggak nyaman.'],
                ['judul' => 'Jangan baca hal bikin cemas', 'deskripsi' => 'Saran saya, jangan banyak baca hal-hal yang bikin cemas di internet. Fokus sama kondisi kita masing-masing.'],
                ['judul' => 'Pijat prenatal', 'deskripsi' => 'Pijat prenatal itu beneran bantu banget ngilangin pegal-pegal. Recommended!'],
                ['judul' => 'Musik buat janin', 'deskripsi' => 'Buat yang suka musik, dengerin musik lembut itu bantu banget buat menenangkan janin juga ibu.'],
                ['judul' => 'Persiapan menyusui', 'deskripsi' => 'Mulai cari info tentang ASI sejak hamil bun. Persiapan menyusui itu penting.'],
            ],
            'Kegiatan Kehamilan' => [
                ['judul' => 'Senam hamil di puskesmas', 'deskripsi' => 'Kemarin saya ikut senam hamil di puskesmas dekat rumah. Seru banget! Bisa ketemu bumil lain.'],
                ['judul' => 'Belajar merajut', 'deskripsi' => 'Saya mulai belajar merajut buat bayi nanti. Hasilnya belum rapi tapi seneng sih.'],
                ['judul' => 'Jalan-jalan ke mall', 'deskripsi' => 'Weekend kemarin saya dan suami jalan-jalan ke mall. Jalan santai tapi pegel juga haha.'],
                ['judul' => 'Kelas prenatal', 'deskripsi' => 'Saya ikut kelas prenatal di rumah sakit. Dapat banyak ilmu tentang persiapan persalinan.'],
                ['judul' => 'Mulai baby shopping', 'deskripsi' => 'Mulai deh baby shopping! Beli some baby clothes dan perlengkapan lain. Seneng banget!'],
                ['judul' => 'Hypnobirthing class', 'deskripsi' => 'Saya dan suami ikut kelas hypnobirthing. Teknik pernapasannya beneran membantu.'],
                ['judul' => 'Me time nonton film', 'deskripsi' => 'Nonton film tentang kehamilan sambil makan ice cream. Me time terbaik!'],
                ['judul' => 'Arisan ibu-ibu', 'deskripsi' => 'Ikut arisan ibu-ibu di komplek. Dapat teman curhat dan dapat ilmu juga.'],
                ['judul' => 'Buku harian kehamilan', 'deskripsi' => 'Saya coba bikin buku harian kehamilan. Nanti bisa dibaca sama bayi pas besar.'],
                ['judul' => 'Maternity photoshoot', 'deskripsi' => 'Foto maternity shoot kemaren sama suami. Hasilnya bagus banget!'],
            ],
            'Obat-obatan Kehamilan' => [
                ['judul' => 'Obat sakit kepala aman?', 'deskripsi' => 'Bun, tanya ya. Obat sakit kepala apa yang aman buat ibu hamil? Saya pusing banget.'],
                ['judul' => 'Vitamin tambahan folat', 'deskripsi' => 'Dokter saya kasih vitamin tambahan folat. Ada yang ngalamin juga minum suplemen folat?'],
                ['judul' => 'Obat maag saat hamil', 'deskripsi' => 'Ada yang pernah minum obat maag saat hamil? Saya maagnya kambuh nih.'],
                ['judul' => 'Obat penambah darah', 'deskripsi' => 'Saya diberi obat penambah darah karena Hb rendah. Ada efek samping nggak ya?'],
                ['judul' => 'Susu kehamilan enak', 'deskripsi' => 'Tanya, susu kehamilan yang enak dan nggak bikin mual apa ya bun?'],
                ['judul' => 'Vitamin C tiap hari', 'deskripsi' => 'Vitamin C aman nggak ya diminum tiap hari saat hamil? Saya pengen daya tahan tubuh naik.'],
                ['judul' => 'Obat gatal kulit', 'deskripsi' => 'Ada yang pernah pakai obat gatal untuk ibu hamil? Kulit saya gatal-gatal di area perut.'],
                ['judul' => 'Suplemen omega-3', 'deskripsi' => 'Minum suplemen omega-3 saat hamil itu penting nggak sih? Ada saran merek yang bagus?'],
                ['judul' => 'Krim stretch mark', 'deskripsi' => 'Saya kasih krim stretch mark. Ada rekomendasi merek yang ampuh?'],
                ['judul' => 'Pil tidur untuk ibu hamil', 'deskripsi' => 'Pil tidur itu aman nggak buat ibu hamil yang susah tidur? Saya butuh istirahat.'],
            ],
            'Rekomendasi Kehamilan' => [
                ['judul' => 'Buku kehamilan buat pemula', 'deskripsi' => 'Rekomendasi buku tentang kehamilan buat ibu hamil pemula dong bun?'],
                ['judul' => 'Aplikasi tracking kehamilan', 'deskripsi' => 'Ada yang punya rekomendasi aplikasi tracking kehamilan yang bagus? Saya butuh monitor.'],
                ['judul' => 'Dokter kandungan recommended', 'deskripsi' => 'Dokter kandungan di daerah sini mana yang recommended bun? Butuh referensi.'],
                ['judul' => 'RS persalinan nyaman', 'deskripsi' => 'RS dengan fasilitas persalinan yang bagus dan nyaman di mana ya? Mohon infonya.'],
                ['judul' => 'Bidan ramah profesional', 'deskripsi' => 'Ada yang punya rekomendasi bidan yang ramah dan profesional?'],
                ['judul' => 'Toko baju hamil', 'deskripsi' => 'Baju hamil yang nyaman dan affordable di beli di mana ya bun?'],
                ['judul' => 'Bantal hamil bagus', 'deskripsi' => 'Bantal hamil yang bagus merek apa ya? Saya butuh tidur yang nyenyak.'],
                ['judul' => 'Cemilan sehat bumil', 'deskripsi' => 'Rekomendasi makanan sehat buat ngemil tapi nggak bikin gemuk?'],
                ['judul' => 'Kelas yoga hamil', 'deskripsi' => 'Ada yang punya rekomendasi kelas yoga hamil yang bagus?'],
                ['judul' => 'Lotion stretch mark', 'deskripsi' => 'Cream atau lotion untuk atasi stretch mark yang recommended?'],
            ],
        ];

        $userIds = $ibuHamilUsers;

        // Shuffle user IDs agar post lebih acak
        shuffle($userIds);

        // Generate 2 post per user
        foreach ($userIds as $userId) {
            // Pilih 2 kategori berbeda untuk setiap user
            $selectedCategories = array_rand($categories, 2);

            foreach ($selectedCategories as $categoryIndex) {
                $category = $categories[$categoryIndex];
                $posts = $postsData[$category];
                $postData = $posts[array_rand($posts)];

                // Generate likes_count dan komen count secara acak
                $likesCount = rand(0, 15);
                $commentsCount = rand(0, 8);

                // Insert post
                DB::table('komunitas')->insert([
                    'user_id' => (string)$userId,
                    'judul' => $postData['judul'],
                    'deskripsi' => $postData['deskripsi'],
                    'gambar' => null,
                    'komen' => $commentsCount,
                    'apresiasi' => $likesCount,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $postId = DB::getPdo()->lastInsertId();

                // Buat reply dari user lain secara acak (1-3 replies per post)
                $numReplies = rand(1, 3);
                $potentialRepliers = array_filter($userIds, function($id) use ($userId) {
                    return $id !== $userId;
                });

                if (!empty($potentialRepliers)) {
                    $replierKeys = array_rand($potentialRepliers, min($numReplies, count($potentialRepliers)));

                    $replies = [
                        'Iya bun, saya juga ngalamin hal yang sama. Semangat ya!',
                        'Wah sama banget bun! Saya juga.',
                        'Tips yang bagus banget bun, makasih infonya.',
                        'Aamiyan, semoga lancar sampai persalinan ya bun.',
                        'Bisa sharing lebih lanjut nggak bun? Saya pengen tau lebih banyak.',
                        'Saya dulu juga ngalamin gitu bun. Alhamdulillah lancar aja.',
                        'Recommended banget tempat yang bun sebutin. Saya pernah ke sana.',
                        'Terima kasih infonya ya bun, sangat membantu.',
                        'Wah boleh dicoba nih. Makasih sharingnya!',
                        'Semangat bun! Kita semua bisa lewatin ini.',
                        'Bisa kasih tahu lebih detail nggak bun?',
                        'Iya bun setuju. Penting banget jaga kesehatan.',
                        'Saya doain yang terbaik ya bun.',
                        'Wah pengalaman yang menarik bun. Terima kasih sudah sharing.',
                        'Alhamdulillah bun, semoga bayinya sehat selalu.',
                    ];

                    foreach ((array)$replierKeys as $key) {
                        $replierId = $potentialRepliers[$key];
                        $replyContent = $replies[array_rand($replies)];

                        DB::table('komentarkomunitas')->insert([
                            'post_id' => $postId,
                            'user_id' => (string)$replierId,
                            'komentar' => $replyContent,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }
                }
            }
        }

        $totalPosts = count($userIds) * 2;
        $this->command->info("Successfully created {$totalPosts} komunitas posts with comments for " . count($userIds) . " ibu hamil users.");
    }
}
