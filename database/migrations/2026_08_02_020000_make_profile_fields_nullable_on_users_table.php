<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Unanswered profile fields must be NULL, not silently defaulted.
 *
 * The old defaults ('beginner', 'general_fitness', 3 days) made every new
 * user look fully profiled, so the trainer could never know what to ask.
 * The profile-intake system treats NULL / 0 as "not answered yet".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('experience_level')->nullable()->change();
            $table->string('primary_goal')->nullable()->change();
            $table->tinyInteger('training_days_per_week')->unsigned()->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('experience_level')->default('beginner')->change();
            $table->string('primary_goal')->default('general_fitness')->change();
            $table->tinyInteger('training_days_per_week')->unsigned()->default(3)->change();
        });
    }
};
