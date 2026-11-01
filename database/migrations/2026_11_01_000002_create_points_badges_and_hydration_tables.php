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
        // 1. Tambah kolom poin di tabel users jika belum ada
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'total_points')) {
                $table->unsignedInteger('total_points')->default(0)->after('category');
            }
            if (!Schema::hasColumn('users', 'points_balance')) {
                $table->unsignedInteger('points_balance')->default(0)->after('total_points');
            }
        });

        // 2. Buat tabel point_transactions untuk mencatat riwayat poin
        if (!Schema::hasTable('point_transactions')) {
            Schema::create('point_transactions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->integer('points'); // bisa positif (+ reward) atau negatif (- pemakaian)
                $table->string('action_type', 50); // daily_task, hydration, streak_bonus, badge_bonus
                $table->string('reference_id', 100)->nullable(); // ID tugas atau badge_code
                $table->string('description', 255);
                $table->timestamps();

                $table->foreign('user_id')
                    ->references('user_id')
                    ->on('users')
                    ->onDelete('cascade');

                $table->index(['user_id', 'created_at']);
                $table->index('action_type');
            });
        }

        // 3. Buat tabel master badges
        if (!Schema::hasTable('badges')) {
            Schema::create('badges', function (Blueprint $table) {
                $table->id();
                $table->string('badge_code', 60)->unique();
                $table->string('name', 100);
                $table->string('category', 50); // medis, hidrasi, nutrisi, streak, aktivitas, gaya_hidup
                $table->text('description');
                $table->string('icon', 100)->default('emoji_events');
                $table->unsignedInteger('bonus_points')->default(0);
                $table->string('requirement_type', 50); // streak_days, ttd_count, hydration_streak, food_compliance_count, exercise_count, early_bird
                $table->unsignedInteger('requirement_threshold')->default(1);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // 4. Buat tabel pivot user_badges
        if (!Schema::hasTable('user_badges')) {
            Schema::create('user_badges', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('badge_id');
                $table->timestamp('unlocked_at')->useCurrent();
                $table->timestamps();

                $table->foreign('user_id')
                    ->references('user_id')
                    ->on('users')
                    ->onDelete('cascade');

                $table->foreign('badge_id')
                    ->references('id')
                    ->on('badges')
                    ->onDelete('cascade');

                $table->unique(['user_id', 'badge_id']);
            });
        }

        // 5. Tambah kolom glass_count dan is_target_reached pada tabel water_intakes
        Schema::table('water_intakes', function (Blueprint $table) {
            if (!Schema::hasColumn('water_intakes', 'glass_count')) {
                $table->unsignedTinyInteger('glass_count')->default(1)->after('user_id');
            }
            if (!Schema::hasColumn('water_intakes', 'is_target_reached')) {
                $table->boolean('is_target_reached')->default(false)->after('jumlah_ml');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('water_intakes', function (Blueprint $table) {
            if (Schema::hasColumn('water_intakes', 'is_target_reached')) {
                $table->dropColumn('is_target_reached');
            }
            if (Schema::hasColumn('water_intakes', 'glass_count')) {
                $table->dropColumn('glass_count');
            }
        });

        Schema::dropIfExists('user_badges');
        Schema::dropIfExists('badges');
        Schema::dropIfExists('point_transactions');

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'points_balance')) {
                $table->dropColumn('points_balance');
            }
            if (Schema::hasColumn('users', 'total_points')) {
                $table->dropColumn('total_points');
            }
        });
    }
};
