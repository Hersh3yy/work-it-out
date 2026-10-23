<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ExerciseEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'workout_session_id',
    'activity_log_id',
    'exercise_name',
    'sets',
    'reps',
    'weight_kg',
    'duration_seconds',
    'distance_meters',
    'notes',
    'sort_order',
])]
class ExerciseEntry extends Model
{
    /** @use HasFactory<ExerciseEntryFactory> */
    use HasFactory, HasUlids;

    protected function casts(): array
    {
        return [
            'weight_kg' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<WorkoutSession, $this> */
    public function workoutSession(): BelongsTo
    {
        return $this->belongsTo(WorkoutSession::class);
    }

    /** @return HasMany<ExerciseSet, $this> */
    public function setRows(): HasMany
    {
        return $this->hasMany(ExerciseSet::class)->orderBy('position');
    }

    /**
     * Replace this exercise's sets and refresh the summary columns: the number
     * of sets, the reps when every set has the same reps, the top weight.
     *
     * @param  list<array{reps: int|null, weight_kg: float|null}>  $sets
     */
    public function replaceSets(array $sets): void
    {
        $this->setRows()->delete();

        foreach (array_values($sets) as $index => $set) {
            $this->setRows()->create([
                'position' => $index + 1,
                'reps' => $set['reps'],
                'weight_kg' => $set['weight_kg'],
            ]);
        }

        $this->refreshSummary();
    }

    /**
     * Fill a value that was missing on every set (the answer to "how many
     * reps?" or "what weight?"), keeping the values sets already have.
     */
    public function fillMissingOnSets(string $column, int|float $value): void
    {
        $this->setRows()->whereNull($column)->update([$column => $value]);
        $this->refreshSummary();
    }

    /**
     * Total weight moved: the sum of reps times weight over the sets, or the
     * summary columns for an entry without set rows.
     */
    public function volumeKg(): float
    {
        $sets = $this->relationLoaded('setRows') ? $this->setRows : $this->setRows()->get();

        if ($sets->isEmpty()) {
            return (float) ($this->sets ?? 0) * (float) ($this->reps ?? 0) * (float) ($this->weight_kg ?? 0);
        }

        return (float) $sets->sum(fn (ExerciseSet $set): float => (float) ($set->reps ?? 0) * (float) ($set->weight_kg ?? 0));
    }

    private function refreshSummary(): void
    {
        $sets = $this->setRows()->get();

        if ($sets->isEmpty()) {
            return;
        }

        $reps = $sets->pluck('reps')->unique();
        $weights = $sets->pluck('weight_kg')->filter(fn ($w): bool => $w !== null)->map(fn ($w): float => (float) $w);

        $this->update([
            'sets' => $sets->count(),
            'reps' => $reps->count() === 1 ? $reps->first() : null,
            'weight_kg' => $weights->isEmpty() ? null : $weights->max(),
        ]);

        $this->unsetRelation('setRows');
    }
}
