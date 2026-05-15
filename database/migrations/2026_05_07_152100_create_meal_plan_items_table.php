<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meal_plan_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meal_plan_id')
                ->constrained('meal_plans')
                ->onDelete('cascade');
            $table->foreignId('food_id')
                ->nullable()
                ->constrained('foods')
                ->nullOnDelete();

            $table->unsignedTinyInteger('day_index'); // 0..6
            $table->string('slot', 20); // breakfast, lunch, dinner, snack
            $table->string('focus_nutrient', 40)->nullable();
            $table->json('food_snapshot');
            $table->timestamps();

            $table->unique(['meal_plan_id', 'day_index', 'slot']);
            $table->index(['meal_plan_id', 'day_index']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meal_plan_items');
    }
};
