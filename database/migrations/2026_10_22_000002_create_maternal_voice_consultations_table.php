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
        Schema::create('maternal_voice_consultations', function (Blueprint $table) {
            $table->id('consultation_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('pregnancy_id')->nullable();
            
            $table->string('audio_file_path', 255)->nullable();
            $table->text('transcribed_text')->nullable();
            $table->longText('ai_answer');
            $table->string('reference_source', 255)->nullable();
            $table->string('provider_used', 50)->default('groq');
            
            // Status Kegawatdaruratan
            $table->boolean('is_emergency')->default(false);
            $table->enum('danger_level', ['NORMAL', 'WARNING', 'EMERGENCY'])->default('NORMAL');
            $table->json('danger_keywords')->nullable();
            $table->text('recommended_action')->nullable();
            
            $table->timestamps();

            // Index & Foreign Keys
            $table->foreign('user_id')->references('user_id')->on('users')->onDelete('cascade');
            $table->index(['user_id', 'is_emergency']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('maternal_voice_consultations');
    }
};
