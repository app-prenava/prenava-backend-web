<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use App\Models\User;
use App\Models\DeviceToken;
use Illuminate\Support\Facades\DB;
use App\Support\AuthToken;
use Illuminate\Support\Facades\Config;

class UploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup a fake local storage disk
        Storage::fake('public');
        
        Config::set('jwt.secret', 'testing_dummy_secret_12345678901234567890');
    }

    private function createUserWithToken(string $role = 'ibu_hamil'): array
    {
        $email = 'test_' . uniqid() . '@example.com';
        $password = 'password123';

        $userId = DB::table('users')->insertGetId([
            'role' => $role,
            'email' => $email,
            'name' => 'Test User',
            'password' => bcrypt($password),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::find($userId);

        $this->withoutExceptionHandling();

        $response = $this->postJson('/api/auth/login', [
            'email' => $email,
            'password' => $password,
        ]);

        $token = $response->json('authorization.token') ?? $response->json('access_token');

        if (!$token) {
            dd('LOGIN FAILED:', $response->json());
        }

        return [$user, $token];
    }

    public function test_can_upload_shop_photo()
    {
        [$user, $token] = $this->createUserWithToken('ibu_hamil');

        // Create a fake image file
        $file = UploadedFile::fake()->image('test_product.jpg');

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->post('/api/shop/create', [
            'product_name' => 'Produk Test Upload',
            'price' => '50000',
            'url' => 'https://example.com/buy',
            'photo' => $file,
        ]);

        $response->assertStatus(201)
                 ->assertJsonPath('status', 'success');

        // Assert the file was stored securely in the local public disk
        $uploadedPath = DB::table('shop')->where('product_name', 'Produk Test Upload')->first()->photo;
        
        // Check that the file actually exists on the disk
        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk('public');
        $disk->assertExists($uploadedPath);
    }
    
    public function test_can_upload_banner_image()
    {
        [$admin, $token] = $this->createUserWithToken('admin');

        // Create a fake image file
        $file = UploadedFile::fake()->image('test_banner.png');

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->post('/api/banner/create', [
            'name' => 'Promo Diskon Test',
            'is_active' => true,
            'url' => 'https://example.com/promo',
            'photo' => $file,
        ]);

        $response->assertStatus(201)
                 ->assertJsonPath('status', 'success');

        // Assert the file was stored securely in the local public disk
        $uploadedPath = DB::table('ad_banner')->where('name', 'Promo Diskon Test')->first()->photo;
        
        // Check that the file actually exists on the disk
        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk('public');
        $disk->assertExists($uploadedPath);
    }
}
