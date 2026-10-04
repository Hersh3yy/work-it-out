<?php

declare(strict_types=1);

namespace App\Channels;

use App\Actions\SmartLog\AnswerOpenQuestion;
use App\Actions\SmartLog\RecordSmartLog;
use App\Actions\SmartLog\RevertSmartLog;
use App\Enums\LogSource;
use App\Exceptions\AiUnavailable;
use App\Interpretation\Interpreter;
use App\Models\ChannelIdentity;
use App\Models\User;

/**
 * The conversation core every chat provider shares: message in, reply text out.
 *
 * Checked in this order: unlinked sender (ignored), /start and /help, /undo,
 * an answer to the open question (no AI), then the interpreter (classify,
 * parse, named rules) and the recorder. It knows nothing about Telegram; a
 * WhatsApp adapter would call the same method.
 */
final readonly class HandleInboundMessage
{
    public const string HELP = "Text me what you did, for example:\n"
        ."bench 3x8 80kg, then incline db 3x10 30\n"
        ."ran 5k in 28 min\n"
        ."104.5kg (your weight)\n\n"
        ."If I ask for a missing number, just reply with it, or say skip.\n"
        .'/undo removes your last log.';

    public function __construct(
        private Interpreter $interpreter,
        private RecordSmartLog $record,
        private RevertSmartLog $revert,
        private AnswerOpenQuestion $answers,
        private ReplyComposer $replies,
    ) {}

    /**
     * The reply to send, or null when the sender is not linked to a user.
     */
    public function handle(InboundMessage $message): ?string
    {
        $user = ChannelIdentity::query()
            ->where('provider', $message->provider->value)
            ->where('external_id', $message->senderId)
            ->first()
            ?->user;

        if ($user === null) {
            logger()->info('Chat message from unlinked sender', [
                'provider' => $message->provider->value,
                'sender_id' => $message->senderId,
            ]);

            return null;
        }

        $text = mb_substr(trim($message->text), 0, 2000);
        $command = mb_strtolower((string) strtok($text, ' @'));

        return match ($command) {
            '/start', '/help' => self::HELP,
            '/undo' => $this->undo($user),
            default => $this->freeText($user, $text, $message->provider->logSource()),
        };
    }

    private function freeText(User $user, string $text, LogSource $source): string
    {
        $open = $this->answers->openFor($user);

        if ($open !== null && in_array(mb_strtolower($text), ['skip', '/skip'], true)) {
            $this->answers->skip($open);

            return 'OK, left it empty.';
        }

        $answered = $open !== null ? $this->answers->answer($open, $text) : null;

        if ($answered !== null) {
            return 'Updated: '.$this->replies->entry($answered);
        }

        try {
            $interpretation = $this->interpreter->interpret($user, $text);
        } catch (AiUnavailable) {
            return "Couldn't reach the AI just now, so nothing was saved. Try again in a minute.";
        }

        if (! $interpretation->shouldRecord()) {
            return (string) $interpretation->reply;
        }

        return $this->replies->receipt($this->record->handle($user, $text, $interpretation->parsed, $source));
    }

    private function undo(User $user): string
    {
        $log = $user->activityLogs()->where('created_at', '>=', now()->subDay())->first();

        if ($log === null) {
            return 'Nothing to undo from the last 24 hours.';
        }

        $entries = $log->exerciseEntries()->orderBy('sort_order')->get();
        $removed = $entries->isEmpty()
            ? $log->summary
            : $entries->map($this->replies->entry(...))->implode(', ');

        $this->revert->handle($log);

        return "Removed: {$removed}";
    }
}
