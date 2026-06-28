<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exercise_entries', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('workout_session_id')->constrained()->cascadeOnDelete();
            $table->string('exercise_name', 100);
            $table->tinyInteger('sets')->unsigned()->nullable();
            $table->tinyInteger('reps')->unsigned()->nullable();
            $table->decimal('weight_kg', 6, 2)->nullable();
            $table->integer('duration_seconds')->unsigned()->nullable()->comment('For cardio/timed exercises');
            $table->integer('distance_meters')->unsigned()->nullable()->comment('For running etc.');
            $table->text('notes')->nullable();
            $table->tinyInteger('sort_order')->unsigned()->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exercise_entries');
    }
};
