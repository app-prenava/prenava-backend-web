<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('email_verified_at')->nullable()->after('email');
            $table->string('google_id')->nullable()->unique()->after('token_version');
            $table->string('auth_provider')->default('email')->after('google_id');
        });

        // Mark all existing users as verified so they can still login
        DB::table('users')->whereNull('email_verified_at')->update([
            'email_verified_at' => now(),
            'auth_provider' => 'email',
        ]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['email_verified_at', 'google_id', 'auth_provider']);
        });
    }
};
