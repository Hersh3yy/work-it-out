<?php

declare(strict_types=1);

namespace App\Conversation\Commands;

use App\Models\User;
use App\Profile\ProfileSummary;

final readonly class ProfileCommand implements ChatCommand
{
    public function __construct(
        private ProfileSummary $summary,
    ) {}

    public function names(): array
    {
        return ['me', 'profile'];
    }

    public function menuDescription(): string
    {
        return 'Your profile and notes';
    }

    public function reply(User $user, string $argument = ''): string
    {
        return $this->summary->for($user);
    }
}
