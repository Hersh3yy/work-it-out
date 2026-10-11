<?php

declare(strict_types=1);

namespace App\Channels;

use App\Actions\Linking\RedeemLinkCode;
use App\Actions\SmartLog\AnswerOpenQuestion;
use App\Actions\SmartLog\RecordSmartLog;
use App\Actions\SmartLog\RevertSmartLog;
use App\Enums\LogSource;
use App\Exceptions\AiUnavailable;
use App\Interpretation\Interpreter;
use App\Models\ChannelIdentity;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;

/**
 * The conversation core every chat provider shares: message in, reply text out.
 *
 * Checked in this order: unlinked sender (an invite code links them, anyone
 * else gets one polite reply a day), /start and /help, /undo,
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

    public const string STRANGER = 'Hi! Feetness is in private beta. Ask the person who invited you for a link, then tap it to connect.';

    public const string LINK_FAILED = "That link didn't work. It may have expired or been used already, so ask for a new one.";

    private const int MAX_LINK_ATTEMPTS = 5;

    public function __construct(
        private Interpreter $interpreter,
        private RecordSmartLog $record,
        private RevertSmartLog $revert,
        private AnswerOpenQuestion $answers,
        private ReplyComposer $replies,
        private RedeemLinkCode $links,
    ) {}

    /**
     * The reply to send, or null when an unlinked sender is being kept quiet.
     */
    public function handle(InboundMessage $message): ?string
    {
        $user = ChannelIdentity::query()
            ->where('provider', $message->provider->value)
            ->where('external_id', $message->senderId)
            ->first()
            ?->user;

        if ($user === null) {
            return $this->stranger($message);
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

    /**
     * An unlinked sender: a valid invite code links them; anyone else gets the
     * private-beta reply once a day. Five wrong codes in an hour means silence.
     */
    private function stranger(InboundMessage $message): ?string
    {
        $sender = $message->provider->value.':'.$message->senderId;

        if (preg_match('/^\/(?:start|link)(?:@\w+)?\s+([A-Za-z0-9_-]{6,64})\s*$/', trim($message->text), $m)) {
            $attempts = 'link-attempts:'.$sender;

            if (RateLimiter::tooManyAttempts($attempts, self::MAX_LINK_ATTEMPTS)) {
                return null;
            }

            $user = $this->links->handle($message->provider, $message->senderId, $m[1]);

            if ($user === null) {
                RateLimiter::hit($attempts, 3600);

                return self::LINK_FAILED;
            }

            RateLimiter::clear($attempts);

            return "Welcome, {$user->name}! Your account is connected.\n\n".self::HELP;
        }

        logger()->info('Chat message from unlinked sender', ['provider' => $message->provider->value, 'sender_id' => $message->senderId]);

        $replied = 'stranger-reply:'.$sender;

        if (RateLimiter::tooManyAttempts($replied, 1)) {
            return null;
        }

        RateLimiter::hit($replied, 86400);

        return self::STRANGER;
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
