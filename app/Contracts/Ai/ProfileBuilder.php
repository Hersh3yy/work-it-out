<?php

declare(strict_types=1);

namespace App\Contracts\Ai;

use App\Exceptions\AiUnavailable;
use App\Profile\ProfileFacts;

/**
 * Port for writing the qualitative profile: short observations about the user
 * from computed facts, the previous observations and recent logs.
 *
 * The adapter only words things. Numbers come from ProfileFacts (plain code),
 * and UpdateProfile checks every citation before anything is saved.
 *
 * @see https://refactoring.guru/design-patterns/adapter
 */
interface ProfileBuilder
{
    /**
     * @param  list<array{text: string, cites: list<string>}>  $previousNotes
     * @param  list<array{id: string, date: string, type: string, message: string, saved_as: string}>  $recentLogs
     * @return list<array{text: string, cites: list<string>}>
     *
     * @throws AiUnavailable
     */
    public function build(ProfileFacts $facts, array $previousNotes, array $recentLogs): array;

    /** The model name stored with each version, for later comparison. */
    public function modelName(): ?string;
}
