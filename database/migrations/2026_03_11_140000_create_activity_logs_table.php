<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_name')->nullable();
            $table->string('user_email')->nullable();
            $table->string('user_role')->nullable();
            $table->string('activity_type', 50); // login, logout, register, update_profile, change_password, deactivated, activated, deteksi_depresi, deteksi_anemia, rekomendasi_makanan, rekomendasi_gerakan
            $table->string('activity_label'); // Label deskriptif untuk ditampilkan di frontend
            $table->text('description')->nullable(); // Deskripsi detail aktivitas
            $table->json('metadata')->nullable(); // Data tambahan dalam format JSON
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('activity_type');
            $table->index('created_at');
            $table->index(['user_id', 'activity_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
