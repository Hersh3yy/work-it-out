<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What kind of message came in, decided before anything is parsed.
 */
enum MessageKind: string
{
    case Workout = 'workout';
    case BodyWeight = 'body_weight';
    case GoalOrInfo = 'goal_or_info';
    case CoachQuestion = 'coach_question';
    case Command = 'command';
    case Unknown = 'unknown';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Kinds whose facts go through the parser and into the log.
     */
    public function isLoggable(): bool
    {
        return in_array($this, [self::Workout, self::BodyWeight, self::GoalOrInfo], true);
    }
}
