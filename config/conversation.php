<?php

declare(strict_types=1);

use App\Conversation\Answers\ExerciseQuestionAnswer;
use App\Conversation\Answers\LimitationChoiceAnswer;
use App\Conversation\Answers\ProfileQuestionAnswer;
use App\Conversation\Answers\RedFlagScreenAnswer;
use App\Conversation\Commands\BetterCommand;
use App\Conversation\Commands\HelpCommand;
use App\Conversation\Commands\LoginCommand;
use App\Conversation\Commands\ProfileCommand;
use App\Conversation\Commands\UndoCommand;

return [

    /*
    | A message the AI could not handle is kept and retried after these many
    | seconds, one try each (App\Jobs\ProcessWaitingMessage); after the last
    | one the person is told it did not go through.
    */
    'retry_delays' => [60, 180, 600, 1800],

    /*
    | Slash commands, in the order Telegram's menu shows them
    | (php artisan channel:telegram:setup reads this list).
    */
    'commands' => [
        HelpCommand::class,
        UndoCommand::class,
        ProfileCommand::class,
        LoginCommand::class,
        BetterCommand::class,
    ],

    /*
    | Open questions a plain message can answer without AI, tried in this
    | order. A question about the log itself always wins over a profile question.
    */
    'answer_handlers' => [
        ExerciseQuestionAnswer::class,
        LimitationChoiceAnswer::class,
        RedFlagScreenAnswer::class,
        ProfileQuestionAnswer::class,
    ],

];
