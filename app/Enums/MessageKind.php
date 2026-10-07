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
     * What each kind means, in the words a classifier model is given.
     * Command is left out: commands start with "/" and are caught by plain rules.
     *
     * @return array<string, string> kind value => description
     */
    public static function criteria(): array
    {
        return [
            self::Workout->value => 'training that happened or is being reported, with or without numbers: lifts, sets, reps, runs, sports',
            self::BodyWeight->value => "the user's own body weight, a number with kg or a word like weighed",
            self::GoalOrInfo->value => 'a goal, a plan, or something about the user that is not a logged session: pain, sleep, a target',
            self::CoachQuestion->value => 'a question asking for training advice or feedback',
            self::Unknown->value => 'not about training, the body, goals or advice',
        ];
    }

    /**
     * Kinds whose facts go through the parser and into the log.
     */
    public function isLoggable(): bool
    {
        return in_array($this, [self::Workout, self::BodyWeight, self::GoalOrInfo], true);
    }
}
