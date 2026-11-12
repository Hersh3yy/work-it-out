<?php

declare(strict_types=1);

namespace App\Conversation;

use App\Actions\Coach\AnswerCoachQuestion;
use App\Actions\SmartLog\RecordSmartLog;
use App\Channels\ReplyComposer;
use App\Coach\AsksTheCoach;
use App\Enums\LogSource;
use App\Exceptions\AiUnavailable;
use App\Interpretation\Interpreter;
use App\Limitations\LimitationCheck;
use App\Models\User;
use App\Profile\ProfileCheckIn;

/**
 * Free text that needs the AI: the interpreter decides between a log, a
 * limitation and a coach question, and this acts on it. A message can do
 * two things at once: log a workout and say how the back is, or log and
 * ask ("bench 3x5 70, what next?"); then the receipt comes first and the
 * coach answers the same message after it. Used by the chat front desk and,
 * when the AI was down, by the job that retries a kept message, so both
 * take exactly the same path.
 */
final readonly class RespondToText
{
    public function __construct(
        private Interpreter $interpreter,
        private RecordSmartLog $record,
        private ReplyComposer $replies,
        private LimitationCheck $limitationCheck,
        private ProfileCheckIn $checkIn,
        private LimitationConversation $limitations,
        private AnswerCoachQuestion $coach,
    ) {}

    /**
     * @throws AiUnavailable
     */
    public function reply(User $user, string $text, LogSource $source): string
    {
        $interpretation = $this->interpreter->interpret($user, $text);

        if ($interpretation->isLimitation()) {
            return $this->thenTheCoach($user, $text, $this->limitations->reply($user, $text, $interpretation->limitation, $source));
        }

        if ($interpretation->isCoachQuestion() || ($interpretation->isNotUnderstood() && $this->coach->isTalkingWith($user))) {
            return $this->coach->handle($user, $text);
        }

        if (! $interpretation->shouldRecord()) {
            return (string) $interpretation->reply;
        }

        $result = $this->record->handle($user, $text, $interpretation->parsed, $source);
        $lines = [$this->replies->receipt($result)];
        $limitationNote = $interpretation->alsoAboutLimitation()
            ? $this->limitations->noteOnLog($user, $text, $interpretation->limitation, $source, $result->log)
            : null;
        $lines[] = $limitationNote;

        if ($result->log->questions === null || $result->log->questions === []) {
            $lines[] = $limitationNote === null ? $this->limitationCheck->for($user, $result->entries) : null;
            $lines[] = $this->checkIn->next($user);
        }

        return $this->thenTheCoach($user, $text, implode("\n\n", array_filter($lines)));
    }

    private function thenTheCoach(User $user, string $text, string $reply): string
    {
        return AsksTheCoach::in($text) ? $reply."\n\n".$this->coach->handle($user, $text) : $reply;
    }
}
