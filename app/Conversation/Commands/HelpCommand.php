<?php

declare(strict_types=1);

namespace App\Conversation\Commands;

use App\Models\User;

final readonly class HelpCommand implements ChatCommand
{
    public const string TEXT = "Text me what you did, for example:\n"
        ."bench 3x8 80kg, then incline db 3x10 30\n"
        ."ran 5k in 28 min\n"
        ."104.5kg (your weight)\n\n"
        ."If I ask for a missing number, just reply with it, or say skip.\n"
        ."/undo removes your last log.\n"
        ."/me shows your profile, /login opens the app.\n\n"
        ."Ask me anything about your training: \"30 min, 3 things, go\", \"foam roller for my back?\"\n\n"
        ."Tell me about an injury (\"hurt my back, going slow for 2 weeks\") and how it is going (\"back is better today\"); /better when it has healed.\n\n"
        .'Every morning at 8 the coach texts you what today is for; /daily 7 moves it, /daily off stops it.';

    public function names(): array
    {
        return ['help', 'start'];
    }

    public function menuDescription(): string
    {
        return 'How to log a workout';
    }

    public function reply(User $user, string $argument = ''): string
    {
        return self::TEXT;
    }
}
