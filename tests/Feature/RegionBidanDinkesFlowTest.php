<?php

namespace Tests\Feature;

use App\Models\BidanApplication;
use App\Models\BidanLocation;
use App\Models\BidanProfile;
use App\Models\BidanSubscription;
use App\Models\HealthScanHistory;
use App\Models\StuntingPrediction;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\UserDinkes;
use App\Models\UserProfile;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\Helpers\JwtTestHelpers;
use Tests\TestCase;

class RegionBidanDinkesFlowTest extends TestCase
{
    use RefreshDatabase;
    use JwtTestHelpers;

    protected function seedMinimalRegions(): void
    {
        DB::table('provinces')->insert([
            ['code' => '32', 'name' => 'Jawa Barat'],
            ['code' => '35', 'name' => 'Jawa Timur'],
        ]);

        DB::table('regencies')->insert([
            ['code' => '3273', 'province_code' => '32', 'name' => 'Kota Bandung'],
            ['code' => '3578', 'province_code' => '35', 'name' => 'Kota Surabaya'],
        ]);

        DB::table('districts')->insert([
            ['code' => '327301', 'regency_code' => '3273', 'name' => 'Sukasari'],
            ['code' => '327302', 'regency_code' => '3273', 'name' => 'Coblong'],
            ['code' => '357801', 'regency_code' => '3578', 'name' => 'Tegalsari'],
        ]);
    }

    public function test_public_region_endpoints_return_hierarchy(): void
    {
        $this->seedMinimalRegions();

        $this->getJson('/api/public/regions/provinces')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->getJson('/api/public/regions/regencies?province_code=32')
            ->assertOk()
            ->assertJsonFragment(['code' => '3273', 'name' => 'Kota Bandung']);

        $this->getJson('/api/public/regions/districts?regency_code=3273')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_region_seeder_loads_full_national_kemendagri_dataset(): void
    {
        $this->seed(\Database\Seeders\RegionSeeder::class);

        $this->assertEquals(38, DB::table('provinces')->count());
        $this->assertEquals(514, DB::table('regencies')->count());
        $this->assertGreaterThan(7000, DB::table('districts')->count());
    }

    public function test_bidan_application_requires_license_region_and_coords_and_auto_creates_verified_partner(): void
    {
        Storage::fake('public');
        $this->seedMinimalRegions();

        $plan = SubscriptionPlan::create([
            'name' => 'Mitra Tahunan',
            'description' => 'Paket kemitraan 1 tahun',
            'price' => 500000,
            'duration_days' => 365,
            'features' => ['Listing Terverifikasi'],
            'is_active' => true,
            'sort_order' => 1,
        ]);

        // Missing STR/SIP/document/region/coords -> rejected with 422
        $this->postJson('/api/public/bidan-applications', [
            'subscription_plan_id' => $plan->subscription_plan_id,
            'full_name' => 'Bidan Ani',
            'email' => 'ani@example.com',
            'phone' => '081234567890',
            'bidan_name' => 'PMB Ani',
            'full_address' => 'Jl. Cihampelas No. 1',
        ])->assertStatus(422)
          ->assertJsonValidationErrors(['str_number', 'sip_number', 'province_code', 'regency_code', 'district_code', 'lat', 'lng', 'document_file']);

        // Valid multipart submission with certificate file
        $file = UploadedFile::fake()->create('sertifikat-str-sip.pdf', 120, 'application/pdf');
        $submitRes = $this->post('/api/public/bidan-applications', [
            'subscription_plan_id' => $plan->subscription_plan_id,
            'full_name' => 'Bidan Ani',
            'email' => 'ani@example.com',
            'phone' => '081234567890',
            'bidan_name' => 'PMB Ani Sejahtera',
            'spesialisasi' => 'Kebidanan & ANC',
            'full_address' => 'Jl. Cihampelas No. 1',
            'province_code' => '32',
            'regency_code' => '3273',
            'district_code' => '327302',
            'lat' => -6.8915,
            'lng' => 107.6107,
            'str_number' => 'STR-123456',
            'sip_number' => 'SIPB-654321',
            'document_file' => $file,
        ], ['Accept' => 'application/json']);

        $submitRes->assertStatus(201);
        $appId = $submitRes->json('data.application_id');

        // Admin approves & creates account -> auto creates BidanProfile + primary BidanLocation
        $admin = User::create([
            'name' => 'Super Admin',
            'email' => 'admin@prenava.id',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'is_active' => true,
        ]);
        $adminToken = $this->issueToken($admin);

        $this->withHeader('Authorization', "Bearer {$adminToken}")
            ->patchJson("/api/admin/bidan-applications/{$appId}/approve")
            ->assertOk();

        $createRes = $this->withHeader('Authorization', "Bearer {$adminToken}")
            ->postJson('/api/admin/bidans', [
                'application_id' => $appId,
                'email' => 'ani@example.com',
                'password' => 'secret1234',
            ]);
        $createRes->assertStatus(201);

        $bidanUserId = $createRes->json('data.user_id');
        $this->assertDatabaseHas('bidan_profile', [
            'user_id' => $bidanUserId,
            'tempat_praktik' => 'PMB Ani Sejahtera',
            'regency_code' => '3273',
            'district_code' => '327302',
            'str_number' => 'STR-123456',
            'sip_number' => 'SIPB-654321',
            'is_verified_partner' => 1,
        ]);
        $this->assertDatabaseHas('bidan_locations', [
            'bidan_id' => $bidanUserId,
            'is_primary' => 1,
            'is_active' => 1,
        ]);

        // Unverified bidan (created without approved application/subscription) MUST NOT appear to ibu hamil
        $unverifiedBidan = User::create([
            'name' => 'Bidan Belum Mitra',
            'email' => 'unverified@example.com',
            'password' => Hash::make('secret1234'),
            'role' => 'bidan',
            'is_active' => true,
        ]);
        BidanLocation::create([
            'bidan_id' => $unverifiedBidan->user_id,
            'lat' => -6.8920,
            'lng' => 107.6110,
            'address_label' => 'Jl. Tanpa Izin',
            'is_active' => true,
            'is_primary' => true,
        ]);

        $ibu = User::create([
            'name' => 'Ibu Sari',
            'email' => 'sari@example.com',
            'password' => Hash::make('secret1234'),
            'role' => 'ibu_hamil',
            'is_active' => true,
        ]);
        $ibuToken = $this->issueToken($ibu);

        $locRes = $this->withHeader('Authorization', "Bearer {$ibuToken}")
            ->getJson('/api/user/bidans/locations?lat=-6.8900&lng=107.6100&radius_km=10');

        $locRes->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.bidan_id', $bidanUserId)
            ->assertJsonPath('data.0.is_verified_partner', true)
            ->assertJsonPath('data.0.str_number', 'STR-123456')
            ->assertJsonPath('data.0.district_name', 'Coblong');
    }

    public function test_dinkes_dashboard_is_strictly_scoped_to_own_regency(): void
    {
        $this->seedMinimalRegions();

        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin2@prenava.id',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'is_active' => true,
        ]);
        $adminToken = $this->issueToken($admin);

        // Admin creates Dinkes Kota Bandung (3273)
        $this->withHeader('Authorization', "Bearer {$adminToken}")
            ->postJson('/api/admin/create/account/dinkes', [
                'name' => 'Dinkes Bandung',
                'email' => 'dinkes.bandung@example.com',
                'password' => 'secret1234',
                'province_code' => '32',
                'regency_code' => '3273',
            ])
            ->assertStatus(201);

        $dinkesUser = User::where('email', 'dinkes.bandung@example.com')->firstOrFail();

        // Ibu 1 in Bandung (Coblong), Trimester 2, Anemia + High Risk Stunting
        $ibuBandung = User::create([
            'name' => 'Ibu Bandung',
            'email' => 'ibu.bandung@example.com',
            'password' => Hash::make('secret1234'),
            'role' => 'ibu_hamil',
            'is_active' => true,
        ]);
        UserProfile::create([
            'user_id' => $ibuBandung->user_id,
            'photo' => '',
            'status_kandungan' => 'Trimester 2',
            'provinsi' => 'Jawa Barat',
            'province_code' => '32',
            'kota' => 'Kota Bandung',
            'regency_code' => '3273',
            'kecamatan' => 'Coblong',
            'district_code' => '327302',
            'lat' => -6.89,
            'lng' => 107.61,
        ]);
        // Two anemia scans: old non-anemia, latest anemia -> must count only 1 latest (anemia)
        HealthScanHistory::create([
            'user_id' => $ibuBandung->user_id,
            'type' => 'anemia',
            'result' => ['label' => 'non-anemia'],
            'created_at' => now()->subDays(5),
        ]);
        HealthScanHistory::create([
            'user_id' => $ibuBandung->user_id,
            'type' => 'anemia',
            'result' => ['label' => 'anemia'],
            'created_at' => now(),
        ]);
        StuntingPrediction::create([
            'user_id' => $ibuBandung->user_id,
            'input_data' => [],
            'ml_payload' => [],
            'prediction' => 1,
            'risk_label' => 'high_risk',
            'status' => 'success',
        ]);

        // Ibu 2 in Surabaya (3578) -> MUST NOT leak into Bandung Dinkes dashboard
        $ibuSurabaya = User::create([
            'name' => 'Ibu Surabaya',
            'email' => 'ibu.sby@example.com',
            'password' => Hash::make('secret1234'),
            'role' => 'ibu_hamil',
            'is_active' => true,
        ]);
        UserProfile::create([
            'user_id' => $ibuSurabaya->user_id,
            'photo' => '',
            'status_kandungan' => 'Trimester 1',
            'provinsi' => 'Jawa Timur',
            'province_code' => '35',
            'kota' => 'Kota Surabaya',
            'regency_code' => '3578',
            'kecamatan' => 'Tegalsari',
            'district_code' => '357801',
        ]);

        $dinkesToken = $this->issueToken($dinkesUser);

        $res = $this->withHeader('Authorization', "Bearer {$dinkesToken}")
            ->getJson('/api/dinkes/dashboard');

        $res->assertOk()
            ->assertJsonPath('data.region.regency_code', '3273')
            ->assertJsonPath('data.summary.total_ibu_hamil', 1)
            ->assertJsonPath('data.summary.ibu_hamil_high_risk', 1)
            ->assertJsonPath('data.trimester_distribution.trimester_2', 1)
            ->assertJsonPath('data.trimester_distribution.trimester_1', 0)
            ->assertJsonPath('data.risk_distribution.anemia.anemia', 1)
            ->assertJsonPath('data.risk_distribution.anemia.non_anemia', 0)
            ->assertJsonPath('data.risk_distribution.stunting.tinggi', 1);
    }
}
