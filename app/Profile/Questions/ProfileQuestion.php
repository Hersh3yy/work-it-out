<?php

declare(strict_types=1);

namespace App\Profile\Questions;

use App\Models\User;

/**
 * One fact the profile can ask for. Each question owns its wording, how it
 * reads an answer and where the answer is stored, so adding, removing or
 * rewording a question touches one class (and the list in config/profile.php).
 *
 * Questions ask for facts the user knows, never for judgments about
 * themselves (PLAN.md section 4, "facts, not judgments").
 *
 * @see https://refactoring.guru/design-patterns/strategy
 */
interface ProfileQuestion
{
    /** A stable name, stored with every prompt. */
    public function key(): string;

    public function isAnswered(User $user): bool;

    /** The question as the user reads it. */
    public function ask(User $user): string;

    /**
     * Store the answer and return the confirmation to send, or null when the
     * text is not an answer to this question.
     */
    public function answer(User $user, string $text): ?string;
}
