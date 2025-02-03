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
        Schema::create('exercise_sets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workout_id')->constrained()->onDelete('cascade');
            $table->foreignId('exercise_id')->nullable()->constrained()->onDelete('set null');
            $table->integer('reps')->nullable();
            $table->decimal('weight', 8, 2)->nullable();
            $table->text('notes')->nullable();
            $table->integer('order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exercise_sets');
    }
};
