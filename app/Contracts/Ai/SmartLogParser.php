<?php

declare(strict_types=1);

namespace App\Contracts\Ai;

use App\Models\User;

/**
 * Port for the natural-language activity log parser.
 *
 * Implementations classify a free-text log message and return the structured
 * payload defined by SmartLogAgent::schema() (log_type, exercises, coach
 * feedback, RPG deltas, ...).
 *
 * @see https://refactoring.guru/design-patterns/adapter
 */
interface SmartLogParser
{
    /**
     * @return array<string, mixed> structured log payload
     */
    public function parse(User $user, string $message): array;
}
