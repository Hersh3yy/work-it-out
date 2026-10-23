<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * One row per set, each with its own reps and weight ("squats 5x5,
 * 90 80 80 80 80" is five rows). exercise_entries keeps a summary (number of
 * sets, reps when all sets match, the top weight) so records and existing
 * reads keep working. Existing entries get uniform set rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exercise_sets', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('exercise_entry_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->unsignedSmallInteger('reps')->nullable();
            $table->decimal('weight_kg', 6, 2)->nullable();
            $table->timestamps();

            $table->index(['exercise_entry_id', 'position']);
        });

        DB::table('exercise_entries')
            ->whereNotNull('sets')
            ->where('sets', '>', 0)
            ->orderBy('id')
            ->each(function (object $entry): void {
                $now = now();
                $rows = [];

                foreach (range(1, min((int) $entry->sets, 50)) as $position) {
                    $rows[] = [
                        'id' => (string) Str::ulid(),
                        'exercise_entry_id' => $entry->id,
                        'position' => $position,
                        'reps' => $entry->reps,
                        'weight_kg' => $entry->weight_kg,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                DB::table('exercise_sets')->insert($rows);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('exercise_sets');
    }
};
