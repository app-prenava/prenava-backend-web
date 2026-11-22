<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_profile', function (Blueprint $table) {
            $table->string('province_code', 10)->nullable()->after('provinsi');
            $table->string('regency_code', 10)->nullable()->after('kota');
            $table->string('district_code', 10)->nullable()->after('kecamatan');
            $table->decimal('lat', 10, 8)->nullable()->after('district_code');
            $table->decimal('lng', 11, 8)->nullable()->after('lat');
            $table->timestamp('location_updated_at')->nullable()->after('lng');

            $table->index(['regency_code', 'district_code'], 'idx_user_profile_region');
            $table->index(['lat', 'lng'], 'idx_user_profile_coords');
        });

        Schema::table('bidan_applications', function (Blueprint $table) {
            $table->string('province_code', 10)->nullable()->after('province');
            $table->string('regency_code', 10)->nullable()->after('city');
            $table->string('district', 100)->nullable()->after('regency_code');
            $table->string('district_code', 10)->nullable()->after('district');
            $table->decimal('lat', 10, 8)->nullable()->after('district_code');
            $table->decimal('lng', 11, 8)->nullable()->after('lat');
            $table->string('spesialisasi', 255)->nullable()->after('bidan_name');
            $table->json('operating_hours')->nullable()->after('lng');

            $table->index(['regency_code', 'district_code'], 'idx_bidan_app_region');
        });

        Schema::table('bidan_profile', function (Blueprint $table) {
            $table->string('provinsi_tempat_praktik', 100)->nullable()->after('alamat_praktik');
            $table->string('province_code', 10)->nullable()->after('provinsi_tempat_praktik');
            $table->string('regency_code', 10)->nullable()->after('kota_tempat_praktik');
            $table->string('district_code', 10)->nullable()->after('kecamatan_tempat_praktik');
            $table->string('str_number', 100)->nullable()->after('spesialisasi');
            $table->string('sip_number', 100)->nullable()->after('str_number');
            $table->boolean('is_verified_partner')->default(false)->after('sip_number');
            $table->timestamp('verified_at')->nullable()->after('is_verified_partner');

            $table->index(['regency_code', 'district_code'], 'idx_bidan_profile_region');
        });

        Schema::table('user_dinkes', function (Blueprint $table) {
            $table->string('province_code', 10)->nullable()->after('nip');
            $table->string('regency_code', 10)->nullable()->after('province_code');

            $table->index('regency_code', 'idx_user_dinkes_regency');
        });
    }

    public function down(): void
    {
        Schema::table('user_dinkes', function (Blueprint $table) {
            $table->dropIndex('idx_user_dinkes_regency');
            $table->dropColumn(['province_code', 'regency_code']);
        });

        Schema::table('bidan_profile', function (Blueprint $table) {
            $table->dropIndex('idx_bidan_profile_region');
            $table->dropColumn([
                'provinsi_tempat_praktik',
                'province_code',
                'regency_code',
                'district_code',
                'str_number',
                'sip_number',
                'is_verified_partner',
                'verified_at',
            ]);
        });

        Schema::table('bidan_applications', function (Blueprint $table) {
            $table->dropIndex('idx_bidan_app_region');
            $table->dropColumn([
                'province_code',
                'regency_code',
                'district',
                'district_code',
                'lat',
                'lng',
                'spesialisasi',
                'operating_hours',
            ]);
        });

        Schema::table('user_profile', function (Blueprint $table) {
            $table->dropIndex('idx_user_profile_region');
            $table->dropIndex('idx_user_profile_coords');
            $table->dropColumn([
                'province_code',
                'regency_code',
                'district_code',
                'lat',
                'lng',
                'location_updated_at',
            ]);
        });
    }
};

