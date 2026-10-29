<?php

declare(strict_types=1);

namespace App\Profile;

use App\Contracts\Stats\PersonalRecords;
use App\Models\User;

/**
 * Everything about a user that plain code can work out: what they entered,
 * this week's score, records, body weight trend and totals. The profile
 * writer may quote these numbers but never computes its own.
 */
final readonly class ProfileFacts
{
    /**
     * @param  array<string, mixed>  $data
     */
    private function __construct(private array $data) {}

    public static function for(User $user): self
    {
        $weights = $user->bodyWeightLogs()->orderBy('logged_at')->get(['logged_at', 'weight_kg']);
        $first = $weights->first();
        $latest = $weights->last();
        $lastSession = $user->workoutSessions()->latest('logged_at')->first(['logged_at'])?->logged_at;
        $week = $user->weeklyStats();

        return new self([
            'entered_by_user' => [
                'name' => $user->name,
                'main_goal' => $user->primary_goal?->value,
                'experience' => $user->experience_level?->value,
                'training_days_per_week' => (int) $user->training_days_per_week ?: null,
                'target_weight_kg' => $user->target_weight_kg === null ? null : (float) $user->target_weight_kg,
                'goal_deadline' => $user->goal_deadline?->toDateString(),
            ],
            'this_week' => [
                'sessions' => $week['sessions_this_week'],
                'weekly_score_percent' => (float) $week['adherence_rate'],
                'streak_days' => (int) $week['streak'],
                'volume_kg' => (float) $week['total_volume_kg'],
            ],
            'records' => app(PersonalRecords::class)->for($user),
            'body_weight' => [
                'entries' => $weights->count(),
                'first' => $first === null ? null : ['date' => $first->logged_at->toDateString(), 'kg' => (float) $first->weight_kg],
                'latest' => $latest === null ? null : ['date' => $latest->logged_at->toDateString(), 'kg' => (float) $latest->weight_kg],
                'change_kg' => $first === null || $latest === null ? null : round((float) $latest->weight_kg - (float) $first->weight_kg, 1),
            ],
            'limitations' => $user->limitations()->active()->latest('started_on')->get()->map(static fn ($l): array => [
                'area' => $l->areaLabel(),
                'note' => $l->note,
                'severity' => $l->severity?->value,
                'since' => $l->started_on->toDateString(),
                'until' => $l->expected_until?->toDateString(),
            ])->values()->all(),
            'totals' => [
                'workout_sessions' => $user->workoutSessions()->count(),
                'logged_messages' => $user->activityLogs()->count(),
                'days_since_last_session' => $lastSession === null ? null : (int) $lastSession->startOfDay()->diffInDays(today()),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->data;
    }
}
