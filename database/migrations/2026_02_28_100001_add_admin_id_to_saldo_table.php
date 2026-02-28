<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('saldo', function (Blueprint $table) {
            if (!Schema::hasColumn('saldo', 'admin_id')) {
                $table->unsignedBigInteger('admin_id')->nullable()->after('status');
                $table->foreign('admin_id')
                    ->references('user_id')
                    ->on('users')
                    ->onDelete('set null');
            }
        });
    }

    public function down(): void
    {
        Schema::table('saldo', function (Blueprint $table) {
            $table->dropForeign(['admin_id']);
            $table->dropColumn('admin_id');
        });
    }
};
