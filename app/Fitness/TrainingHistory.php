<?php

declare(strict_types=1);

namespace App\Fitness;

use App\Models\ExerciseEntry;
use App\Models\Limitation;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * What a person trained in the weeks up to one day, loaded once, so every
 * measure reads the same rows as of the same day.
 */
final readonly class TrainingHistory
{
    /**
     * @param  Collection<int, ExerciseEntry>  $entries  each with its workout session and set rows
     * @param  Collection<int, Limitation>  $activeLimitations
     */
    private function __construct(
        public CarbonImmutable $on,
        private Collection $entries,
        public ?float $bodyWeightKg,
        public Collection $activeLimitations,
        public bool $hasEverTrained,
    ) {}

    public static function for(User $user, ?CarbonInterface $on = null): self
    {
        $day = CarbonImmutable::parse($on ?? today())->startOfDay();
        $sessions = $user->workoutSessions()
            ->where('logged_at', '<=', $day->endOfDay())
            ->where('logged_at', '>', $day->subDays((int) config('fitness.history_days'))->endOfDay())
            ->with('exerciseEntries.setRows')
            ->get();

        return new self(
            $day,
            $sessions->flatMap(static fn ($session): Collection => $session->exerciseEntries->each->setRelation('workoutSession', $session))->values(),
            self::bodyWeightOn($user, $day),
            $user->limitations()->where('started_on', '<=', $day->toDateString())
                ->where(static fn ($q) => $q->whereNull('resolved_at')->orWhere('resolved_at', '>', $day->endOfDay()))
                ->get(),
            $user->workoutSessions()->where('logged_at', '<=', $day->endOfDay())->exists(),
        );
    }

    /**
     * Entries from the last $days days, today included.
     *
     * @return Collection<int, ExerciseEntry>
     */
    public function within(int $days): Collection
    {
        return $this->entries->filter(fn (ExerciseEntry $entry): bool => $this->daysAgo($entry) < $days)->values();
    }

    /**
     * @return Collection<int, ExerciseEntry>
     */
    public function training(Quality $quality, int $days): Collection
    {
        return $this->within($days)->filter(static fn (ExerciseEntry $entry): bool => ExerciseQualities::trains($entry->exercise_name, $quality))->values();
    }

    public function daysAgo(ExerciseEntry $entry): int
    {
        return (int) $entry->workoutSession->logged_at->copy()->startOfDay()->diffInDays($this->on);
    }

    /** Days in the last $days without a workout session. */
    public function restDaysIn(int $days): int
    {
        $trainedDays = $this->within($days)->map(fn (ExerciseEntry $entry): int => $this->daysAgo($entry))->unique()->count();

        return $days - $trainedDays;
    }

    private static function bodyWeightOn(User $user, CarbonImmutable $day): ?float
    {
        $weighIn = $user->bodyWeightLogs()->where('logged_at', '<=', $day->toDateString())->latest('logged_at')->value('weight_kg');
        $weight = $weighIn ?? $user->current_weight_kg;

        return $weight === null ? null : (float) $weight;
    }
}
