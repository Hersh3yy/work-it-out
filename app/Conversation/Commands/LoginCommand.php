<?php

declare(strict_types=1);

namespace App\Conversation\Commands;

use App\Actions\Auth\IssueLoginLink;
use App\Models\User;

final readonly class LoginCommand implements ChatCommand
{
    public function __construct(
        private IssueLoginLink $loginLinks,
    ) {}

    public function names(): array
    {
        return ['login', 'app'];
    }

    public function menuDescription(): string
    {
        return 'Open the Feetness app';
    }

    public function reply(User $user, string $argument = ''): string
    {
        return 'Your sign-in link for the Feetness app (works once, '.IssueLoginLink::VALID_MINUTES." minutes):\n".$this->loginLinks->handle($user);
    }
}
