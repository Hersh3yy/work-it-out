<?php

declare(strict_types=1);

namespace App\Conversation\Commands;

use App\Fitness\QualityPower;
use App\Goals\GoalReadiness;
use App\Goals\GoalReply;
use App\Models\Goal;
use App\Models\User;

final readonly class GoalsCommand implements ChatCommand
{
    public function names(): array
    {
        return ['goals'];
    }

    public function menuDescription(): string
    {
        return 'Your goals and how ready you are';
    }

    public function reply(User $user, string $argument = ''): string
    {
        $goals = $user->goals()->ahead()->get();

        if ($goals->isEmpty()) {
            return GoalReply::NONE;
        }

        $power = QualityPower::for($user);

        return $goals->map(static fn (Goal $goal): string => GoalReply::line(
            $goal,
            $goal->daysLeft() === null ? null : GoalReadiness::for($goal, $power, $goal->daysLeft()),
        ))->implode("\n");
    }
}
