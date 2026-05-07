<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('food_recipes', function (Blueprint $table) {
            $table->id();
            $table->string('recipe_hash', 64)->unique();
            $table->string('title');
            $table->string('title_cleaned')->nullable()->index();
            $table->longText('ingredients')->nullable();
            $table->longText('steps')->nullable();
            $table->unsignedInteger('loves')->nullable()->index();
            $table->string('source_url')->nullable()->index();
            $table->string('category', 100)->nullable()->index();
            $table->unsignedSmallInteger('total_ingredients')->nullable();
            $table->unsignedSmallInteger('total_steps')->nullable();
            $table->longText('ingredients_cleaned')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('food_recipes');
    }
};
