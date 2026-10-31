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
        Schema::table('maternal_triage_scores', function (Blueprint $table) {
            if (! Schema::hasColumn('maternal_triage_scores', 'completeness_percentage')) {
                $table->decimal('completeness_percentage', 5, 2)->default(0.00)->after('plate_score');
                $table->unsignedTinyInteger('modalities_count')->default(0)->after('completeness_percentage');
                $table->json('missing_modalities')->nullable()->after('modalities_count');
                $table->boolean('is_dynamically_weighted')->default(true)->after('missing_modalities');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('maternal_triage_scores', function (Blueprint $table) {
            if (Schema::hasColumn('maternal_triage_scores', 'completeness_percentage')) {
                $table->dropColumn([
                    'completeness_percentage',
                    'modalities_count',
                    'missing_modalities',
                    'is_dynamically_weighted',
                ]);
            }
        });
    }
};
