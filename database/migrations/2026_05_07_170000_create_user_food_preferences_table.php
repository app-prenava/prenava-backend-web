<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_food_preferences', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->foreign('user_id')
                ->references('user_id')
                ->on('users')
                ->onDelete('cascade');

            $table->string('budget_level', 20)->nullable(); // low|mid|high
            $table->json('preferred_categories')->nullable();
            $table->json('excluded_categories')->nullable();
            $table->json('excluded_keywords')->nullable(); // e.g. ["pedas","jerohan"]
            $table->json('allergies')->nullable(); // e.g. ["udang"]
            $table->string('diet_style', 30)->nullable(); // omnivore|vegetarian|pescatarian
            $table->boolean('avoid_spicy')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('budget_level');
            $table->index('diet_style');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_food_preferences');
    }
};
