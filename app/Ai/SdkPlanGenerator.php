<?php

declare(strict_types=1);

namespace App\Ai;

use App\Ai\Agents\PlanAgent;
use App\Contracts\Ai\PlanGenerator;
use App\Contracts\Profile\ProfileIntake;
use App\Contracts\Stats\PersonalRecords;
use App\Enums\PlanType;
use App\Models\User;

/**
 * Laravel AI SDK adapter for the PlanGenerator port.
 */
final readonly class SdkPlanGenerator implements PlanGenerator
{
    public function __construct(
        private PersonalRecords $records,
        private ProfileIntake $intake,
    ) {}

    public function generate(User $user, PlanType $type): string
    {
        $agent = new PlanAgent(
            user: $user,
            planType: $type,
            personalRecords: $this->records->for($user),
            intake: $this->intake->report($user),
        );

        $response = $agent->forUser($user)->prompt(
            "Generate my {$type->value} plan for this week."
        );

        return (string) $response;
    }
}
