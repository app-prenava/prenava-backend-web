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
        Schema::table('stunting_predictions', function (Blueprint $table) {
            $table->json('ai_meal_plan')->nullable()->after('cached_ai_support');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stunting_predictions', function (Blueprint $table) {
            $table->dropColumn('ai_meal_plan');
        });
    }
};
