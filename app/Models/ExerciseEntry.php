<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ExerciseEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'workout_session_id',
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
}
