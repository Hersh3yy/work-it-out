<?php

declare(strict_types=1);

namespace App\Goals;

use App\Fitness\QualityPower;
use App\Models\Goal;
use App\Models\User;

/**
 * The goals still ahead, nearest first, each with how ready the person is
 * for it. Undated goals come last, without readiness.
 */
final readonly class GoalFacts
{
    /**
     * @param  list<array{goal: Goal, readiness: GoalReadiness|null}>  $goals
     */
    private function __construct(private array $goals) {}

    public static function for(User $user, QualityPower $power): self
    {
        return new self($user->goals()->ahead()->get()->map(static fn (Goal $goal): array => [
            'goal' => $goal,
            'readiness' => $goal->daysLeft() === null ? null : GoalReadiness::for($goal, $power, $goal->daysLeft()),
        ])->values()->all());
    }

    /** "Nearest goal: rave on Sun 13 Dec, 28 days to go: build. Endurance close, ..." or null. */
    public function nearestLine(): ?string
    {
        $nearest = $this->goals[0] ?? null;

        return $nearest === null || $nearest['readiness'] === null
            ? null
            : 'Nearest goal: '.lcfirst(GoalReply::line($nearest['goal'], $nearest['readiness']));
    }

    /**
     * @return list<array{name: string, kind: string, in_their_words: string, date: string|null, readiness: array{phase: string, days_left: int, qualities: array<string, string>}|null}>
     */
    public function toArray(): array
    {
        return array_map(static fn (array $row): array => [
            'name' => $row['goal']->name,
            'kind' => $row['goal']->kind->value,
            'in_their_words' => $row['goal']->description,
            'date' => $row['goal']->target_on?->toDateString(),
            'readiness' => $row['readiness']?->toArray(),
        ], $this->goals);
    }
}
