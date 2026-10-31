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
        Schema::create('food_scans', function (Blueprint $table) {
            $table->id('food_scan_id');
            $table->foreignId('user_id')->constrained('users', 'user_id')->onDelete('cascade');
            $table->foreignId('pregnancy_id')->nullable()->constrained('pregnancies', 'pregnancy_id')->onDelete('set null');
            $table->string('image_path')->nullable();
            $table->unsignedTinyInteger('nutrition_score')->default(0); // 0 - 100
            $table->string('balance_status', 30)->default('KURANG_SEIMBANG'); // SEIMBANG, CUKUP_SEIMBANG, KURANG_SEIMBANG
            $table->boolean('has_iron_protein')->default(false);
            $table->boolean('has_folate_vegetable')->default(false);
            $table->boolean('has_fruit')->default(false);
            $table->boolean('has_carbohydrate')->default(false);
            $table->unsignedInteger('total_items_detected')->default(0);
            $table->json('detected_items')->nullable();
            $table->json('proportion_breakdown')->nullable();
            $table->text('clinical_feedback')->nullable();
            $table->json('actionable_recommendations')->nullable();
            $table->boolean('coin_rewarded')->default(false);
            $table->decimal('coin_amount', 8, 2)->default(0.00);
            $table->timestamps();

            // Indexing untuk pencarian cepat triase klinis bidan & riwayat pasien
            $table->index(['user_id', 'created_at']);
            $table->index('balance_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('food_scans');
    }
};
