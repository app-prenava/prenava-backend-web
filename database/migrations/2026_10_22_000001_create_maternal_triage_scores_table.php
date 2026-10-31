<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('maternal_triage_scores', function (Blueprint $table) {
            $table->id();

            // Pasien (Ibu Hamil) & Rekam Kehamilan
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('pregnancy_id')->nullable();

            // Skor Agregat Klinis (0.00 s/d 1.00) & Kategori Prioritas
            $table->decimal('triage_score', 4, 2)->default(0.00);
            $table->enum('triage_category', ['MERAH', 'KUNING', 'HIJAU'])->default('HIJAU');

            // Komponen Sub-skor Terbobot
            $table->decimal('stunting_score', 4, 2)->default(0.00); // Bobot 35%
            $table->decimal('anemia_score', 4, 2)->default(0.00);   // Bobot 30%
            $table->decimal('bp_score', 4, 2)->default(0.00);       // Bobot 20%
            $table->decimal('plate_score', 4, 2)->default(0.00);    // Bobot 15%

            // Rangkuman Pemicu Kegawatan & Saran Intervensi Medis
            $table->string('primary_alert')->nullable();
            $table->text('suggested_action')->nullable();
            $table->timestamp('calculated_at')->useCurrent();

            $table->timestamps();

            // Relasi Foreign Key
            $table->foreign('user_id')
                ->references('user_id')
                ->on('users')
                ->onDelete('cascade');

            $table->foreign('pregnancy_id')
                ->references('pregnancy_id')
                ->on('pregnancies')
                ->onDelete('cascade');

            // Indeks untuk Optimasi Query Antrean CDSS Bidan
            $table->index(['user_id', 'calculated_at']);
            $table->index('triage_category');
            $table->index('triage_score');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('maternal_triage_scores');
    }
};
