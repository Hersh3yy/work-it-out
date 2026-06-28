<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workout_sessions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('logged_at');
            $table->smallInteger('duration_minutes')->unsigned()->nullable();
            $table->tinyInteger('perceived_exertion')->unsigned()->nullable()->comment('RPE 1–10');
            $table->tinyInteger('energy_level')->unsigned()->nullable()->comment('1–5 pre-workout energy');
            $table->text('notes')->nullable();
            $table->boolean('completed_planned')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workout_sessions');
    }
};
