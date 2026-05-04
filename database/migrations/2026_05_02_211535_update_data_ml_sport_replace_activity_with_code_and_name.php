<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('data_ml_sport', function (Blueprint $table) {
            // Hapus kolom activity, tambah code + name
            $table->dropUnique(['activity']);
            $table->dropColumn('activity');

            $table->string('code', 100)->unique()->after('id');
            $table->string('name', 150)->after('code');
        });
    }

    public function down(): void
    {
        Schema::table('data_ml_sport', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn(['code', 'name']);

            $table->string('activity', 100)->unique()->after('id');
        });
    }
};