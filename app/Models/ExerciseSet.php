<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One set of an exercise: reps at a weight, a time, a distance, and how hard
 * it was (RPE 1-10, or reps left in reserve; 0 in reserve is to failure).
 *
 * @property string $id
 * @property string $exercise_entry_id
 * @property int $position
 * @property int|null $reps
 * @property string|null $weight_kg
 * @property int|null $duration_seconds
 * @property int|null $distance_meters
 * @property string|null $rpe
 * @property int|null $rir
 */
#[Fillable(['exercise_entry_id', 'position', 'reps', 'weight_kg', 'duration_seconds', 'distance_meters', 'rpe', 'rir'])]
final class ExerciseSet extends Model
{
    use HasUlids;

    public const array VALUES = ['reps', 'weight_kg', 'duration_seconds', 'distance_meters', 'rpe', 'rir'];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'reps' => 'integer',
            'weight_kg' => 'decimal:2',
            'duration_seconds' => 'integer',
            'distance_meters' => 'integer',
            'rpe' => 'decimal:1',
            'rir' => 'integer',
        ];
    }

    /** @return BelongsTo<ExerciseEntry, $this> */
    public function entry(): BelongsTo
    {
        return $this->belongsTo(ExerciseEntry::class, 'exercise_entry_id');
    }

    public function isTimed(): bool
    {
        return $this->duration_seconds !== null && $this->reps === null;
    }

    public function toFailure(): bool
    {
        return $this->rir === 0;
    }

    /**
     * @return array{reps: int|null, weight_kg: float|null, duration_seconds: int|null, distance_meters: int|null, rpe: float|null, rir: int|null}
     */
    public function values(): array
    {
        return [
            'reps' => $this->reps,
            'weight_kg' => $this->weight_kg === null ? null : (float) $this->weight_kg,
            'duration_seconds' => $this->duration_seconds,
            'distance_meters' => $this->distance_meters,
            'rpe' => $this->rpe === null ? null : (float) $this->rpe,
            'rir' => $this->rir,
        ];
    }
}
