<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provinces', function (Blueprint $table) {
            $table->string('code', 10)->primary();
            $table->string('name', 120);
        });

        Schema::create('regencies', function (Blueprint $table) {
            $table->string('code', 10)->primary();
            $table->string('province_code', 10);
            $table->string('name', 120);

            $table->index('province_code');
            $table->foreign('province_code')
                ->references('code')
                ->on('provinces')
                ->onDelete('cascade');
        });

        Schema::create('districts', function (Blueprint $table) {
            $table->string('code', 10)->primary();
            $table->string('regency_code', 10);
            $table->string('name', 120);

            $table->index('regency_code');
            $table->foreign('regency_code')
                ->references('code')
                ->on('regencies')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('districts');
        Schema::dropIfExists('regencies');
        Schema::dropIfExists('provinces');
    }
};

