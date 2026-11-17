<?php

declare(strict_types=1);

use App\Channels\Telegram\TelegramChannel;
use App\Conversation\Answers\ExerciseQuestionAnswer;
use App\Conversation\Answers\GoalDateAnswer;
use App\Conversation\Answers\KeptMessageStatusAnswer;
use App\Conversation\Answers\LimitationChoiceAnswer;
use App\Conversation\Answers\ProfileQuestionAnswer;
use App\Conversation\Answers\RedFlagScreenAnswer;
use App\Conversation\Commands\BetterCommand;
use App\Conversation\Commands\DailyCommand;
use App\Conversation\Commands\GoalsCommand;
use App\Conversation\Commands\HelpCommand;
use App\Conversation\Commands\LoginCommand;
use App\Conversation\Commands\ProfileCommand;
use App\Conversation\Commands\UndoCommand;
use App\Enums\ChatProvider;

return [

    /*
    | A message the AI could not handle is kept and retried after these many
    | seconds, one try each (App\Jobs\ProcessWaitingMessage); after the last
    | one the person is told it did not go through. Almost four hours in all:
    | every job runs on one model with no fallback, the person was told there
    | is no need to resend, and a log saved late is still a log (16 Nov: a
    | workout was saved on the fourth and last try, 30 minutes late).
    */
    'retry_delays' => [60, 180, 600, 1800, 3600, 7200],

    /*
    | Messages that need the AI one person may send a minute before the
    | friendly "send it again in a minute" (App\Conversation\ChatThrottle).
    */
    'ai_messages_per_minute' => (int) env('CHAT_AI_MESSAGES_PER_MINUTE', 6),

    /*
    | The adapter that sends on each chat provider when the app writes first
    | or answers later. Providers left out are never sent to.
    */
    'channels' => [
        ChatProvider::Telegram->value => TelegramChannel::class,
    ],

    /*
    | Slash commands, in the order Telegram's menu shows them
    | (php artisan channel:telegram:setup reads this list).
    */
    'commands' => [
        HelpCommand::class,
        UndoCommand::class,
        ProfileCommand::class,
        GoalsCommand::class,
        LoginCommand::class,
        BetterCommand::class,
        DailyCommand::class,
    ],

    /*
    | Open questions a plain message can answer without AI, tried in this
    | order. A question about the log itself always wins over a profile question.
    */
    'answer_handlers' => [
        ExerciseQuestionAnswer::class,
        LimitationChoiceAnswer::class,
        RedFlagScreenAnswer::class,
        GoalDateAnswer::class,
        ProfileQuestionAnswer::class,
        KeptMessageStatusAnswer::class,
    ],

];
