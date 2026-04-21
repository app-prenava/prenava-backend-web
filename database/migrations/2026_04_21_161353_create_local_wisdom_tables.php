<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('local_wisdom', function (Blueprint $table) {
            $table->id();
            $table->string('myth');
            $table->text('reason');
            $table->string('region');
            $table->timestamps();
        });

        Schema::create('user_wisdom_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users', 'user_id')->cascadeOnDelete();
            $table->foreignId('local_wisdom_id')->constrained('local_wisdom')->cascadeOnDelete();
            $table->date('checked_date');
            $table->timestamps();

            // Unique per user, per mitos, per hari agar reset harian
            $table->unique(['user_id', 'local_wisdom_id', 'checked_date'], 'user_wisdom_daily_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_wisdom_logs');
        Schema::dropIfExists('local_wisdom');
    }
};
