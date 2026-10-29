<?php

declare(strict_types=1);

namespace App\Channels;

use App\Actions\Auth\IssueLoginLink;
use App\Actions\Limitations\RecordLimitation;
use App\Actions\Limitations\ResolveLimitation;
use App\Actions\Linking\RedeemLinkCode;
use App\Actions\SmartLog\RecordSmartLog;
use App\Actions\SmartLog\RevertSmartLog;
use App\Conversation\Answers\AnswerChain;
use App\Enums\LogSource;
use App\Exceptions\AiUnavailable;
use App\Interpretation\Interpreter;
use App\Limitations\LimitationCheck;
use App\Models\ChannelIdentity;
use App\Models\ChannelLinkCode;
use App\Models\User;
use App\Profile\ProfileCheckIn;
use App\Profile\ProfileSummary;
use Illuminate\Support\Facades\RateLimiter;

/**
 * The conversation core every chat provider shares: message in, reply text out.
 *
 * Checked in this order: unlinked sender (an invite code links them, anyone
 * else gets one polite reply a day), commands (/help, /undo, /me, /login,
 * /better), the answer chain (open log question, limitation choice, profile
 * question; no AI), then the interpreter (classify, parse, named rules) and
 * the recorder. A receipt may end with a limitation check and one profile
 * question. It knows nothing about Telegram; a WhatsApp adapter would call
 * the same method.
 */
final readonly class HandleInboundMessage
{
    public const string HELP = "Text me what you did, for example:\n"
        ."bench 3x8 80kg, then incline db 3x10 30\n"
        ."ran 5k in 28 min\n"
        ."104.5kg (your weight)\n\n"
        ."If I ask for a missing number, just reply with it, or say skip.\n"
        ."/undo removes your last log.\n"
        ."/me shows your profile, /login opens the app.\n"
        .'Tell me about an injury ("hurt my back, going slow for 2 weeks") and I\'ll keep it in mind; /better when it has healed.';

    public const string STRANGER = 'Hi! Feetness is in private beta. Ask the person who invited you for a link, then tap it to connect.';

    public const string LINK_FAILED = "That link didn't work. It may have expired or been used already, so ask for a new one.";

    public const string CONNECTED_ELSEWHERE = 'This Telegram account is already connected to another Feetness profile, so that link was not used. Ask the person who invited you to check.';

    private const int MAX_LINK_ATTEMPTS = 5;

    /** "/start CODE" from an invite link, or "/link CODE" typed by hand. */
    private const string START_WITH_CODE = '/^\/(?:start|link)(?:@\w+)?\s+([A-Za-z0-9_-]{6,64})\s*$/';

    public function __construct(
        private Interpreter $interpreter,
        private RecordSmartLog $record,
        private RevertSmartLog $revert,
        private AnswerChain $answers,
        private ReplyComposer $replies,
        private RedeemLinkCode $links,
        private ProfileCheckIn $checkIn,
        private ProfileSummary $profile,
        private RecordLimitation $limitations,
        private ResolveLimitation $resolve,
        private LimitationCheck $limitationCheck,
        private IssueLoginLink $loginLinks,
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

        if (preg_match(self::START_WITH_CODE, $text, $m)) {
            return $this->linkWhileConnected($user, $m[1]);
        }

        $command = mb_strtolower((string) strtok($text, ' @'));

        return match ($command) {
            '/start', '/help' => self::HELP,
            '/undo' => $this->undo($user),
            '/me', '/profile' => $this->profile->for($user),
            '/login', '/app' => $this->login($user),
            '/better', '/healed' => $this->better($user),
            default => $this->freeText($user, $text, $message->provider->logSource()),
        };
    }

    private function freeText(User $user, string $text, LogSource $source): string
    {
        $answered = $this->answers->handle($user, $text);

        if ($answered !== null) {
            return $answered;
        }

        try {
            $interpretation = $this->interpreter->interpret($user, $text);
        } catch (AiUnavailable) {
            return "Couldn't reach the AI just now, so nothing was saved. Try again in a minute.";
        }

        if ($interpretation->isLimitation()) {
            return $this->noteLimitation($user, $text, $interpretation->limitation, $source);
        }

        if (! $interpretation->shouldRecord()) {
            return (string) $interpretation->reply;
        }

        $result = $this->record->handle($user, $text, $interpretation->parsed, $source);
        $lines = [$this->replies->receipt($result)];

        if ($result->log->questions === null || $result->log->questions === []) {
            $lines[] = $this->limitationCheck->for($user, $result->entries);
            $lines[] = $this->checkIn->next($user);
        }

        return implode("\n\n", array_filter($lines));
    }

    /**
     * @param  array<string, mixed>  $facts
     */
    private function noteLimitation(User $user, string $text, array $facts, LogSource $source): string
    {
        $limitation = $this->limitations->handle($user, $text, $facts, $source);
        $question = $limitation->activityLog->questions[0]['question'] ?? null;

        if ($question !== null) {
            return $question;
        }

        return 'Noted: '.$limitation->describe().". I'll check with you when a logged exercise loads it, and keep suggestions light. Say /better when it has healed.";
    }

    private function login(User $user): string
    {
        return 'Your sign-in link for the Feetness app (works once, '.IssueLoginLink::VALID_MINUTES." minutes):\n".$this->loginLinks->handle($user);
    }

    private function better(User $user): string
    {
        $limitation = $user->limitations()->active()->latest('started_on')->first();

        if ($limitation === null) {
            return 'Nothing to resolve: no active injury or limitation on file.';
        }

        $this->resolve->handle($limitation);

        return 'Glad to hear it. '.ucfirst($limitation->areaLabel()).' marked as healed; suggestions go back to normal.';
    }

    /**
     * An unlinked sender: a valid invite code links them; anyone else gets the
     * private-beta reply once a day. Five wrong codes in an hour means silence.
     */
    private function stranger(InboundMessage $message): ?string
    {
        $sender = $message->provider->value.':'.$message->senderId;

        if (preg_match(self::START_WITH_CODE, trim($message->text), $m)) {
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

    /**
     * A connected person taps an invite link again: their own link is used up
     * and they are told they are connected; someone else's link is left alone.
     */
    private function linkWhileConnected(User $user, string $code): string
    {
        $link = ChannelLinkCode::findValid($code);

        if ($link !== null && $link->user_id !== $user->id) {
            return self::CONNECTED_ELSEWHERE;
        }

        $link?->update(['used_at' => now()]);

        return "You're already connected, {$user->name}.\n\n".self::HELP;
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
