<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('screening_questions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('dimension', 32)->index();
            $table->string('dimension_label', 100);
            $table->text('question_text');
            $table->json('options_json');
            $table->unsignedTinyInteger('weight')->default(1);
            $table->unsignedSmallInteger('order_no')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->string('version', 16)->default('v1')->index();
            $table->timestamps();
        });

        Schema::create('screening_categories', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 64)->unique();
            $table->string('internal_name', 150);
            $table->string('display_name', 150);
            $table->text('description');
            $table->string('badge_color', 16)->default('#FA6978');
            $table->json('recommended_features_json');
            $table->timestamps();
        });

        Schema::create('user_screening_sessions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('version', 16)->default('v1');
            $table->json('dimension_scores_json');
            $table->string('primary_category_slug', 64);
            $table->json('secondary_categories_json');
            $table->json('recommended_features_json');
            $table->timestamp('completed_at');
            $table->timestamps();

            $table->foreign('user_id')
                ->references('user_id')
                ->on('users')
                ->onDelete('cascade');

            $table->foreign('primary_category_slug')
                ->references('slug')
                ->on('screening_categories')
                ->onUpdate('cascade')
                ->onDelete('restrict');

            $table->index(['user_id', 'completed_at']);
        });

        Schema::create('user_screening_answers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('session_id');
            $table->unsignedBigInteger('question_id');
            $table->string('selected_value', 32);
            $table->unsignedTinyInteger('score');
            $table->timestamps();

            $table->foreign('session_id')
                ->references('id')
                ->on('user_screening_sessions')
                ->onDelete('cascade');

            $table->foreign('question_id')
                ->references('id')
                ->on('screening_questions')
                ->onDelete('cascade');

            $table->unique(['session_id', 'question_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_screening_answers');
        Schema::dropIfExists('user_screening_sessions');
        Schema::dropIfExists('screening_categories');
        Schema::dropIfExists('screening_questions');
    }
};
