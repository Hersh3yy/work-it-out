<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One set of an exercise: its own reps and its own weight.
 *
 * @property string $id
 * @property string $exercise_entry_id
 * @property int $position
 * @property int|null $reps
 * @property string|null $weight_kg
 */
#[Fillable(['exercise_entry_id', 'position', 'reps', 'weight_kg'])]
final class ExerciseSet extends Model
{
    use HasUlids;

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'reps' => 'integer',
            'weight_kg' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<ExerciseEntry, $this> */
    public function entry(): BelongsTo
    {
        return $this->belongsTo(ExerciseEntry::class, 'exercise_entry_id');
    }
}
