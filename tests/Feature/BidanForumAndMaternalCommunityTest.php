<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Komunitas;
use App\Models\KomentarKomunitas;
use App\Models\Pregnancy;
use App\Models\BidanProfile;
use App\Models\UserNotification;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class BidanForumAndMaternalCommunityTest extends TestCase
{
    use DatabaseTransactions;

    protected User $mother;
    protected User $midwife;
    protected string $motherToken;
    protected string $midwifeToken;

    protected function setUp(): void
    {
        parent::setUp();

        $uniq = uniqid();

        // 1. Setup Ibu Hamil
        $this->mother = User::create([
            'name'              => 'Bunda Sarah ' . $uniq,
            'email'             => "bunda_{$uniq}@prenava.test",
            'password'          => bcrypt('password123'),
            'role'              => 'ibu_hamil',
            'is_active'         => true,
            'email_verified_at' => now(),
        ]);
        $this->motherToken = JWTAuth::fromUser($this->mother);

        // Pasang profil kehamilan aktif (Mg ke-28 = Trimester 3)
        Pregnancy::create([
            'user_id'               => $this->mother->user_id,
            'lmp_date'              => now()->subWeeks(28)->toDateString(),
            'gestational_age_weeks' => 28,
            'status'                => 'active',
        ]);

        // 2. Setup Bidan Terverifikasi
        $this->midwife = User::create([
            'name'              => 'Bidan Siti Nurhaliza ' . $uniq,
            'email'             => "bidan_{$uniq}@prenava.test",
            'password'          => bcrypt('password123'),
            'role'              => 'bidan',
            'is_active'         => true,
            'email_verified_at' => now(),
        ]);
        $this->midwifeToken = JWTAuth::fromUser($this->midwife);

        BidanProfile::create([
            'user_id'                  => $this->midwife->user_id,
            'tempat_praktik'           => 'Klinik Pratama Sehati Mandiri',
            'alamat_praktik'           => 'Jl. Raya Kebidanan Sehat No. 10',
            'kota_tempat_praktik'      => 'Bandung',
            'kecamatan_tempat_praktik' => 'Coblong',
            'spesialisasi'             => 'Kebidanan Maternal & USG Dasar',
        ]);
    }

    public function test_mother_can_create_post_with_category_anonymity_and_auto_gestational_context(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->motherToken)
            ->postJson('/api/komunitas/add', [
                'judul'        => 'Kram perut bagian bawah di malam hari',
                'deskripsi'    => 'Halo dokter dan bidan, sudah dua malam ini perut saya terasa kencang dan sedikit nyeri, apakah ini kontraksi palsu?',
                'kategori'     => 'keluhan_gejala',
                'is_anonymous' => true,
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('komunitas', [
            'user_id'               => $this->mother->user_id,
            'judul'                 => 'Kram perut bagian bawah di malam hari',
            'kategori'              => 'keluhan_gejala',
            'is_anonymous'          => 1,
            'gestational_age_weeks' => 28,
            'trimester'             => 3,
            'has_bidan_reply'       => 0,
        ]);
    }

    public function test_bidan_can_view_threads_queue_and_reply_with_auto_pin_and_notification(): void
    {
        // 1. Buat postingan ibu hamil
        $post = Komunitas::create([
            'user_id'               => $this->mother->user_id,
            'judul'                 => 'Apakah boleh minum es air kelapa saat hamil tua?',
            'deskripsi'             => 'Banyak tetangga bilang air kelapa bikin ketuban bersih tapi ada yang bilang bikin dingin janin...',
            'kategori'              => 'mitos_fakta',
            'is_anonymous'          => false,
            'gestational_age_weeks' => 34,
            'trimester'             => 3,
            'komen'                 => 0,
            'apresiasi'             => 0,
            'has_bidan_reply'       => false,
        ]);

        // 2. Bidan melihat antrean di portal bidan
        $queueResponse = $this->withHeader('Authorization', 'Bearer ' . $this->midwifeToken)
            ->getJson('/api/bidan/forum/threads?tab=needs_reply');

        $queueResponse->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure([
                'stats' => ['needs_reply', 'validated', 'total'],
                'data',
            ]);

        // 3. Bidan mengirim tanggapan klinis panjang (> 300 karakter) dengan saran janji temu
        $clinicalExplanation = str_repeat('Air kelapa muda kaya akan elektrolit alami seperti kalium dan magnesium yang aman dan baik untuk hidrasi ibu hamil. ', 4);
        $this->assertGreaterThan(300, strlen($clinicalExplanation));

        $replyResponse = $this->withHeader('Authorization', 'Bearer ' . $this->midwifeToken)
            ->postJson("/api/bidan/forum/threads/{$post->post_id}/reply", [
                'komentar'            => $clinicalExplanation,
                'suggest_appointment' => true,
            ]);

        $replyResponse->assertStatus(201)
            ->assertJsonPath('status', 'success');

        // 4. Verifikasi status database
        $post->refresh();
        $this->assertTrue((bool) $post->has_bidan_reply);
        $this->assertEquals(1, $post->komen);

        // 5. Verifikasi komentar tersemat
        $this->assertDatabaseHas('komentarkomunitas', [
            'post_id'             => $post->post_id,
            'user_id'             => $this->midwife->user_id,
            'is_bidan_reply'      => 1,
            'is_pinned'           => 1,
            'suggest_appointment' => 1,
        ]);

        // 6. Verifikasi notifikasi terkirim ke ibu hamil
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $this->mother->user_id,
            'type'    => 'bidan_forum_reply',
            'title'   => 'Tanggapan Medis Diterima',
            'is_read' => 0,
        ]);

        // 7. Verifikasi getComments mengurutkan jawaban bidan paling atas dan menyertakan data verifikasi bidan
        $commentsResponse = $this->withHeader('Authorization', 'Bearer ' . $this->motherToken)
            ->getJson("/api/komunitas/komen/{$post->post_id}");

        $commentsResponse->assertStatus(200)
            ->assertJsonPath('status', 'berhasil');

        $firstComment = $commentsResponse->json('data.0');
        $this->assertTrue($firstComment['is_bidan_reply']);
        $this->assertTrue($firstComment['is_pinned']);
        $this->assertTrue($firstComment['suggest_appointment']);
        $this->assertNotNull($firstComment['bidan_profile']);
        $this->assertEquals('Klinik Pratama Sehati Mandiri', $firstComment['bidan_profile']['tempat_praktik']);

        // 8. Verifikasi ibu hamil dapat mengambil notifikasi
        $notifResponse = $this->withHeader('Authorization', 'Bearer ' . $this->motherToken)
            ->getJson('/api/notifications');

        $notifResponse->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('unread_count', 1);
    }
}
