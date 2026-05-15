<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('food_recipes', function (Blueprint $table) {
            $table->index(['category', 'loves']);
            $table->index(['food_id', 'loves']);
        });
    }

    public function down(): void
    {
        Schema::table('food_recipes', function (Blueprint $table) {
            $table->dropIndex(['category', 'loves']);
            $table->dropIndex(['food_id', 'loves']);
        });
    }
};
