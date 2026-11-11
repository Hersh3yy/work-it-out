<?php

declare(strict_types=1);

namespace App\Conversation\Commands;

use App\Models\User;

/**
 * One slash command in the chat. The list in config/conversation.php is the
 * single source for what the bot answers and for the menu Telegram shows.
 *
 * @see https://refactoring.guru/design-patterns/command
 */
interface ChatCommand
{
    /**
     * The words that trigger it, without the slash; the first one is shown in the menu.
     *
     * @return non-empty-list<string>
     */
    public function names(): array;

    /** One line for the bot's menu. */
    public function menuDescription(): string;

    /** @param  string  $argument  whatever followed the command word, trimmed; often empty */
    public function reply(User $user, string $argument = ''): string;
}
