<?php

declare(strict_types=1);

namespace App\Contracts\Ai;

use App\Models\User;

/**
 * Port for the natural-language activity log parser.
 *
 * Implementations turn a free-text log message into facts: log_type,
 * summary, logged_on (Y-m-d or null), workout fields, exercises (name, sets,
 * reps, weight_kg, duration_seconds, distance_meters, notes), meal_type and
 * food_name (until M3), weight_kg_stat, and questions (at most one
 * {field, question} for a value the user did not give). Only those keys;
 * no coach feedback, no stat changes.
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
