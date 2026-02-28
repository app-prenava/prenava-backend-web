<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->text('cancellation_reason')->nullable()->after('rejection_reason');
            $table->date('rescheduled_date')->nullable()->after('cancellation_reason');
            $table->time('rescheduled_time')->nullable()->after('rescheduled_date');
            $table->enum('rescheduled_by', ['user', 'bidan'])->nullable()->after('rescheduled_time');
        });

        // Add 'rescheduled' to the status enum (MySQL only)
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE appointments MODIFY COLUMN status ENUM('requested','accepted','rejected','completed','cancelled','rescheduled') DEFAULT 'requested'");
        }
    }

    public function down(): void
    {
        // Revert status enum (MySQL only)
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE appointments MODIFY COLUMN status ENUM('requested','accepted','rejected','completed','cancelled') DEFAULT 'requested'");
        }

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn(['cancellation_reason', 'rescheduled_date', 'rescheduled_time', 'rescheduled_by']);
        });
    }
};
