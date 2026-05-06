<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('foods', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('category', 100)->nullable();
            $table->float('protein')->default(0);
            $table->float('iron')->nullable();
            $table->float('calcium')->nullable();
            $table->float('vitamin_a')->nullable();
            $table->float('calories')->default(0);
            $table->float('carbohydrates')->default(0);
            $table->float('fat')->default(0);
            $table->text('image_url')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index('protein');
            $table->index('calories');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('foods');
    }
};
