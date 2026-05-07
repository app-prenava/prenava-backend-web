<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('food_recipes', function (Blueprint $table) {
            $table->foreignId('food_id')
                ->nullable()
                ->after('id')
                ->constrained('foods')
                ->nullOnDelete();

            $table->index('food_id');
        });
    }

    public function down(): void
    {
        Schema::table('food_recipes', function (Blueprint $table) {
            $table->dropIndex(['food_id']);
            $table->dropConstrainedForeignId('food_id');
        });
    }
};
