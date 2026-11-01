<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('catatanibu', function (Blueprint $table) {
            if (!Schema::hasColumn('catatanibu', 'systolic_bp')) {
                $table->integer('systolic_bp')->nullable()->after('hasil_kunjungan');
            }
            if (!Schema::hasColumn('catatanibu', 'diastolic_bp')) {
                $table->integer('diastolic_bp')->nullable()->after('systolic_bp');
            }
            if (!Schema::hasColumn('catatanibu', 'weight_kg')) {
                $table->decimal('weight_kg', 5, 2)->nullable()->after('diastolic_bp');
            }
            if (!Schema::hasColumn('catatanibu', 'fundal_height_cm')) {
                $table->decimal('fundal_height_cm', 5, 2)->nullable()->after('weight_kg');
            }
            if (!Schema::hasColumn('catatanibu', 'fetal_heart_rate_bpm')) {
                $table->integer('fetal_heart_rate_bpm')->nullable()->after('fundal_height_cm');
            }
            if (!Schema::hasColumn('catatanibu', 'hemoglobin_gdl')) {
                $table->decimal('hemoglobin_gdl', 4, 2)->nullable()->after('fetal_heart_rate_bpm');
            }
            if (!Schema::hasColumn('catatanibu', 'therapy_notes')) {
                $table->text('therapy_notes')->nullable()->after('hemoglobin_gdl');
            }
            if (!Schema::hasColumn('catatanibu', 'next_visit_date')) {
                $table->date('next_visit_date')->nullable()->after('therapy_notes');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('catatanibu', function (Blueprint $table) {
            $table->dropColumn([
                'systolic_bp',
                'diastolic_bp',
                'weight_kg',
                'fundal_height_cm',
                'fetal_heart_rate_bpm',
                'hemoglobin_gdl',
                'therapy_notes',
                'next_visit_date',
            ]);
        });
    }
};
