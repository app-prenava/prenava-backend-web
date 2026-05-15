<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stunting_predictions', function (Blueprint $table) {
            $table->id();

            // Owner — references users.user_id
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')
                  ->references('user_id')
                  ->on('users')
                  ->onDelete('cascade');

            // Raw humanized input from mobile
            $table->json('input_data');

            // Transformed ML-ready payload sent to FastAPI
            $table->json('ml_payload');

            // Full response body from FastAPI
            $table->json('ml_response')->nullable();

            // Prediction result fields (denormalized for fast queries)
            $table->float('probability')->nullable();
            $table->tinyInteger('prediction')->nullable();         // 0 or 1
            $table->string('risk_label', 30)->nullable();          // low_risk | high_risk

            // SHAP/LIME explanation from FastAPI
            $table->json('explanation')->nullable();

            // Actionable recommendations from FastAPI
            $table->json('recommendations')->nullable();

            // ML model metadata
            $table->string('model_version', 50)->nullable();       // e.g. lr_v1.0
            $table->unsignedInteger('latency_ms')->nullable();     // Round-trip ML call time
            $table->string('status', 20)->default('success');      // success | failed | timeout

            $table->timestamps();

            // Composite index for history queries
            $table->index(['user_id', 'created_at']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stunting_predictions');
    }
};
