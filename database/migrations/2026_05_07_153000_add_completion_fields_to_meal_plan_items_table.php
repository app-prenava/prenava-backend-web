<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meal_plan_items', function (Blueprint $table) {
            $table->boolean('is_completed')->default(false)->after('food_snapshot');
            $table->timestamp('completed_at')->nullable()->after('is_completed');
        });
    }

    public function down(): void
    {
        Schema::table('meal_plan_items', function (Blueprint $table) {
            $table->dropColumn(['is_completed', 'completed_at']);
        });
    }
};
