<?php

declare(strict_types=1);

namespace App\Contracts\Ai;

use App\Enums\PlanType;
use App\Models\User;

/**
 * Port for AI-generated weekly plans (workout or meal).
 *
 * @see https://refactoring.guru/design-patterns/adapter
 */
interface PlanGenerator
{
    /**
     * Generate a personalised weekly plan as markdown.
     */
    public function generate(User $user, PlanType $type): string;
}
