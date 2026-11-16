<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A timed exercise logged before sets could hold a time ("40 second plank,
 * 4 of those") kept its seconds on the entry and left four empty set rows.
 * Each such row now carries the entry's seconds. Safe to run again: a row
 * that already has a time, reps or a weight is left alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('exercise_entries')
            ->whereNotNull('duration_seconds')
            ->whereNull('reps')
            ->whereNull('weight_kg')
            ->orderBy('id')
            ->each(static function (object $entry): void {
                DB::table('exercise_sets')
                    ->where('exercise_entry_id', $entry->id)
                    ->whereNull('reps')
                    ->whereNull('weight_kg')
                    ->whereNull('duration_seconds')
                    ->update(['duration_seconds' => $entry->duration_seconds]);
            });
    }

    public function down(): void {}
};
