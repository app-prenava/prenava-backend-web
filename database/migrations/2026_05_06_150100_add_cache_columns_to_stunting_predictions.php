<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stunting_predictions', function (Blueprint $table) {
            $table->json('cached_recommendations')->nullable()->after('recommendations');
            $table->json('cached_ai_support')->nullable()->after('cached_recommendations');
        });
    }

    public function down(): void
    {
        Schema::table('stunting_predictions', function (Blueprint $table) {
            $table->dropColumn(['cached_recommendations', 'cached_ai_support']);
        });
    }
};
