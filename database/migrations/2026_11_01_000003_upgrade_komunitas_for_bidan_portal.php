<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Upgrade tabel 'komunitas'
        Schema::table('komunitas', function (Blueprint $table) {
            if (!Schema::hasColumn('komunitas', 'kategori')) {
                $table->string('kategori', 50)->default('umum')->after('judul');
            }
            if (!Schema::hasColumn('komunitas', 'is_anonymous')) {
                $table->boolean('is_anonymous')->default(false)->after('kategori');
            }
            if (!Schema::hasColumn('komunitas', 'gestational_age_weeks')) {
                $table->unsignedTinyInteger('gestational_age_weeks')->nullable()->after('is_anonymous');
            }
            if (!Schema::hasColumn('komunitas', 'trimester')) {
                $table->unsignedTinyInteger('trimester')->nullable()->after('gestational_age_weeks');
            }
            if (!Schema::hasColumn('komunitas', 'has_bidan_reply')) {
                $table->boolean('has_bidan_reply')->default(false)->after('komen');
            }
        });

        // Pastikan deskripsi juga bertipe TEXT agar keluhan lengkap ibu hamil tidak terpotong
        DB::statement('ALTER TABLE komunitas MODIFY COLUMN deskripsi TEXT NOT NULL');

        // Tambah indeks jika belum ada
        Schema::table('komunitas', function (Blueprint $table) {
            $table->index(['kategori', 'created_at'], 'idx_komunitas_kategori_created');
            $table->index('has_bidan_reply', 'idx_komunitas_has_bidan_reply');
        });

        // 2. Upgrade tabel 'komentarkomunitas'
        // Ubah kolom komentar dari varchar(255) ke TEXT untuk menampung penjelasan klinis panjang
        DB::statement('ALTER TABLE komentarkomunitas MODIFY COLUMN komentar TEXT NOT NULL');

        Schema::table('komentarkomunitas', function (Blueprint $table) {
            if (!Schema::hasColumn('komentarkomunitas', 'is_bidan_reply')) {
                $table->boolean('is_bidan_reply')->default(false)->after('komentar');
            }
            if (!Schema::hasColumn('komentarkomunitas', 'is_pinned')) {
                $table->boolean('is_pinned')->default(false)->after('is_bidan_reply');
            }
            if (!Schema::hasColumn('komentarkomunitas', 'suggest_appointment')) {
                $table->boolean('suggest_appointment')->default(false)->after('is_pinned');
            }
            if (!Schema::hasColumn('komentarkomunitas', 'bidan_user_id')) {
                $table->unsignedBigInteger('bidan_user_id')->nullable()->after('suggest_appointment');
            }

            $table->index(['post_id', 'is_pinned', 'created_at'], 'idx_komentar_post_pinned_created');
        });

        // 3. Buat tabel 'user_notifications' untuk notifikasi in-app
        if (!Schema::hasTable('user_notifications')) {
            Schema::create('user_notifications', function (Blueprint $table) {
                $table->id();
                $table->string('user_id', 255)->index();
                $table->string('type', 50)->default('general');
                $table->string('title', 255);
                $table->text('message');
                $table->json('data')->nullable();
                $table->boolean('is_read')->default(false)->index();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_notifications');

        Schema::table('komentarkomunitas', function (Blueprint $table) {
            if (Schema::hasColumn('komentarkomunitas', 'bidan_user_id')) {
                $table->dropColumn('bidan_user_id');
            }
            if (Schema::hasColumn('komentarkomunitas', 'suggest_appointment')) {
                $table->dropColumn('suggest_appointment');
            }
            if (Schema::hasColumn('komentarkomunitas', 'is_pinned')) {
                $table->dropColumn('is_pinned');
            }
            if (Schema::hasColumn('komentarkomunitas', 'is_bidan_reply')) {
                $table->dropColumn('is_bidan_reply');
            }
        });

        Schema::table('komunitas', function (Blueprint $table) {
            if (Schema::hasColumn('komunitas', 'has_bidan_reply')) {
                $table->dropColumn('has_bidan_reply');
            }
            if (Schema::hasColumn('komunitas', 'trimester')) {
                $table->dropColumn('trimester');
            }
            if (Schema::hasColumn('komunitas', 'gestational_age_weeks')) {
                $table->dropColumn('gestational_age_weeks');
            }
            if (Schema::hasColumn('komunitas', 'is_anonymous')) {
                $table->dropColumn('is_anonymous');
            }
            if (Schema::hasColumn('komunitas', 'kategori')) {
                $table->dropColumn('kategori');
            }
        });
    }
};
