<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A set can be a time (40 s plank), a distance (400 m interval) or an effort
 * (RPE 8, 2 reps in reserve), not only reps at a weight. An exercise can be
 * done with the body only, on an assisted machine (the kg is help, not load),
 * or inside a superset or circuit with its neighbours.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exercise_sets', function (Blueprint $table): void {
            $table->unsignedInteger('duration_seconds')->nullable()->after('weight_kg');
            $table->unsignedInteger('distance_meters')->nullable()->after('duration_seconds');
            $table->decimal('rpe', 3, 1)->nullable()->after('distance_meters');
            $table->unsignedTinyInteger('rir')->nullable()->after('rpe');
        });

        Schema::table('exercise_entries', function (Blueprint $table): void {
            $table->boolean('bodyweight')->default(false)->after('notes');
            $table->boolean('assisted')->default(false)->after('bodyweight');
            $table->string('group_label', 40)->nullable()->after('assisted');
        });
    }

    public function down(): void
    {
        Schema::table('exercise_sets', function (Blueprint $table): void {
            $table->dropColumn(['duration_seconds', 'distance_meters', 'rpe', 'rir']);
        });

        Schema::table('exercise_entries', function (Blueprint $table): void {
            $table->dropColumn(['bodyweight', 'assisted', 'group_label']);
        });
    }
};
