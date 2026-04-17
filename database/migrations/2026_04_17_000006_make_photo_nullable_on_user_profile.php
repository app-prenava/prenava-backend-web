<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_profile', function (Blueprint $table) {
            $table->string('photo', 255)->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        Schema::table('user_profile', function (Blueprint $table) {
            $table->string('photo', 255)->nullable(false)->change();
        });
    }
};
