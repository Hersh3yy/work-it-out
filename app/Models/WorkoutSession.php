<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\WorkoutSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'user_id',
    'logged_at',
    'duration_minutes',
    'perceived_exertion',
    'energy_level',
    'notes',
    'completed_planned',
])]
class WorkoutSession extends \Illuminate\Database\Eloquent\Model
{
    /** @use HasFactory<WorkoutSessionFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    protected function casts(): array
    {
        return [
            'logged_at'        => 'datetime',
            'completed_planned' => 'boolean',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<ExerciseEntry, $this> */
    public function exerciseEntries(): HasMany
    {
        return $this->hasMany(ExerciseEntry::class)->orderBy('sort_order');
    }

    /**
     * Sessions that fall within the current ISO week (Monday–Sunday).
     *
     * @param  Builder<WorkoutSession>  $query
     * @return Builder<WorkoutSession>
     */
    public function scopeThisWeek(Builder $query): Builder
    {
        return $query->whereBetween('logged_at', [
            now()->startOfWeek(),
            now()->endOfWeek(),
        ]);
    }

    /**
     * Sessions logged within the last N days.
     *
     * @param  Builder<WorkoutSession>  $query
     * @return Builder<WorkoutSession>
     */
    public function scopeLastNDays(Builder $query, int $days): Builder
    {
        return $query->where('logged_at', '>=', now()->subDays($days)->startOfDay());
    }

    /**
     * Sum of (sets × reps × weight_kg) across all exercise entries.
     * Returns 0.0 if no entries have weight data.
     */
    public function getTotalVolumeKgAttribute(): float
    {
        return $this->exerciseEntries->sum(function (ExerciseEntry $entry): float {
            return (float) ($entry->sets ?? 0)
                * (float) ($entry->reps ?? 0)
                * (float) ($entry->weight_kg ?? 0);
        });
    }
}
