<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('foods', function (Blueprint $table) {
            $table->longText('ingredients')->nullable()->after('description');
            $table->longText('steps')->nullable()->after('ingredients');
            $table->string('source_url')->nullable()->after('steps');
            $table->string('recipe_category', 100)->nullable()->after('source_url');
            $table->unsignedInteger('recipe_loves')->nullable()->after('recipe_category');
            $table->unsignedSmallInteger('total_ingredients')->nullable()->after('recipe_loves');
            $table->unsignedSmallInteger('total_steps')->nullable()->after('total_ingredients');
            $table->timestamp('recipe_synced_at')->nullable()->after('total_steps');

            $table->index('recipe_category');
            $table->index('recipe_loves');
        });
    }

    public function down(): void
    {
        Schema::table('foods', function (Blueprint $table) {
            $table->dropIndex(['recipe_category']);
            $table->dropIndex(['recipe_loves']);
            $table->dropColumn([
                'ingredients',
                'steps',
                'source_url',
                'recipe_category',
                'recipe_loves',
                'total_ingredients',
                'total_steps',
                'recipe_synced_at',
            ]);
        });
    }
};
