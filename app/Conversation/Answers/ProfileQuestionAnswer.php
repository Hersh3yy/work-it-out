<?php

declare(strict_types=1);

namespace App\Conversation\Answers;

use App\Models\User;
use App\Profile\ProfileCheckIn;

/**
 * The answer to the open profile question (training days, main goal, ...).
 */
final readonly class ProfileQuestionAnswer implements AnswerHandler
{
    public function __construct(
        private ProfileCheckIn $checkIn,
    ) {}

    public function handle(User $user, string $text): ?string
    {
        return $this->checkIn->answer($user, $text);
    }
}
