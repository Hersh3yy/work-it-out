<?php

declare(strict_types=1);

namespace App\Contracts\Ai;

use App\Exceptions\AiUnavailable;
use App\Profile\ProfileFacts;

/**
 * Port for writing the qualitative profile: labelled observations about the
 * user from computed facts, the previous observations and recent entries
 * (logs and coach questions, newest first).
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
     * @param  list<array{id: string, date: string, type: string, message: string, saved_as: string}>  $recentEntries  logs, and coach questions with id "coach:N"
     * @return list<array{text: string, cites: list<string>}>
     *
     * @throws AiUnavailable
     */
    public function build(ProfileFacts $facts, array $previousNotes, array $recentEntries): array;

    /** The model name stored with each version, for later comparison. */
    public function modelName(): ?string;
}
