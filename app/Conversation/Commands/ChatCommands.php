<?php

declare(strict_types=1);

namespace App\Conversation\Commands;

/**
 * The configured chat commands, looked up by the word someone typed.
 */
final readonly class ChatCommands
{
    public function find(string $word): ?ChatCommand
    {
        $name = ltrim(mb_strtolower($word), '/');

        foreach ($this->all() as $command) {
            if (in_array($name, $command->names(), true)) {
                return $command;
            }
        }

        return null;
    }

    /**
     * @return list<ChatCommand>
     */
    public function all(): array
    {
        return array_map(static fn (string $class): ChatCommand => app($class), config('conversation.commands', []));
    }

    /**
     * @return list<array{command: string, description: string}>
     */
    public function menu(): array
    {
        return array_map(static fn (ChatCommand $c): array => ['command' => $c->names()[0], 'description' => $c->menuDescription()], $this->all());
    }
}
