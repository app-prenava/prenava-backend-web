<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::dropIfExists('meal_plan_items');
        Schema::dropIfExists('meal_plans');
        Schema::dropIfExists('food_recipes');
        Schema::dropIfExists('scraped_foods');
        Schema::dropIfExists('user_food_preferences');
        Schema::dropIfExists('rekomendasi_makanan');
        Schema::dropIfExists('foods');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Not recreating them, as they are intentionally removed for cleanup
    }
};
