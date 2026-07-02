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
        Schema::table('meal_plan_items', function (Blueprint $table) {
            $table->foreignId('scraped_food_id')->nullable()->after('food_id')->constrained('scraped_foods')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('meal_plan_items', function (Blueprint $table) {
            $table->dropForeign(['scraped_food_id']);
            $table->dropColumn('scraped_food_id');
        });
    }
};
