<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\User;
use App\Models\WorkoutSession;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Recomputes and caches fitness stats on the User record after any workout write.
 *
 * Dispatched by WorkoutSessionController::store(). Runs on the Redis queue so it
 * doesn't block the HTTP response. Safe to retry on failure.
 */
final class UpdateUserStats implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly User $user) {}

    public function handle(): void
    {
        $user = $this->user->fresh();

        if ($user === null) {
            return;
        }

        $user->update([
            'weekly_adherence_rate' => $this->computeWeeklyAdherenceRate($user),
            'current_streak_days'   => $this->computeStreakDays($user),
            'last_active_at'        => $this->computeLastActiveAt($user),
        ]);
    }

    /**
     * Adherence = (completed sessions this week) / (planned days per week) × 100.
     * Capped at 100.
     */
    private function computeWeeklyAdherenceRate(User $user): float
    {
        if ($user->training_days_per_week === 0) {
            return 0.0;
        }

        $completedThisWeek = $user->workoutSessions()
            ->thisWeek()
            ->where('completed_planned', true)
            ->count();

        return min(100.0, round(
            ($completedThisWeek / $user->training_days_per_week) * 100,
            2
        ));
    }

    /**
     * Streak = consecutive days (going backward from today) on which at least
     * one workout session was logged. Skips the current day if no session yet today.
     */
    private function computeStreakDays(User $user): int
    {
        $sessionDates = $user->workoutSessions()
            ->whereNotNull('logged_at')
            ->orderByDesc('logged_at')
            ->limit(365)
            ->pluck('logged_at')
            ->map(fn (Carbon $dt): string => $dt->toDateString())
            ->unique()
            ->values()
            ->toArray();

        if (empty($sessionDates)) {
            return 0;
        }

        $streak  = 0;
        $check   = today();

        // If today has no session, start checking from yesterday.
        if (! in_array($check->toDateString(), $sessionDates, true)) {
            $check = $check->subDay();
        }

        while (in_array($check->toDateString(), $sessionDates, true)) {
            $streak++;
            $check = $check->subDay();
        }

        return $streak;
    }

    private function computeLastActiveAt(User $user): ?Carbon
    {
        /** @var WorkoutSession|null $latest */
        $latest = $user->workoutSessions()
            ->latest('logged_at')
            ->first(['logged_at']);

        return $latest?->logged_at;
    }
}
