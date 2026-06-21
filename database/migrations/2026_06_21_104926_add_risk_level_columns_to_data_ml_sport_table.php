<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('data_ml_sport', function (Blueprint $table) {
            $table->enum('risk_level_high', [
                'highly_recommended',
                'allowed_with_caution',
                'avoid',
            ])->nullable()->after('name');

            $table->enum('risk_level_low', [
                'highly_recommended',
                'allowed_with_caution',
                'avoid',
            ])->nullable()->after('risk_level_high');
        });
    }

    public function down(): void
    {
        Schema::table('data_ml_sport', function (Blueprint $table) {
            $table->dropColumn(['risk_level_high', 'risk_level_low']);
        });
    }
};