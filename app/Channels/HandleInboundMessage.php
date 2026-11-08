<?php

declare(strict_types=1);

namespace App\Channels;

use App\Actions\Conversation\KeepMessage;
use App\Actions\Linking\RedeemLinkCode;
use App\Conversation\Answers\AnswerChain;
use App\Conversation\Commands\ChatCommands;
use App\Conversation\Commands\HelpCommand;
use App\Conversation\RespondToText;
use App\Exceptions\AiUnavailable;
use App\Models\ChannelIdentity;
use App\Models\ChannelLinkCode;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;

/**
 * The conversation core every chat provider shares: message in, reply text out.
 *
 * In this order: an unlinked sender (an invite code links them, anyone else
 * gets one polite reply a day), a slash command, an answer to an open
 * question (no AI; words after the answer go on as their own message), then
 * the AI path (RespondToText: a log, a limitation or a coach question). When
 * the AI is down the message is kept and answered later instead of lost. It
 * knows nothing about Telegram; a WhatsApp adapter would call the same method.
 */
final readonly class HandleInboundMessage
{
    public const string STRANGER = 'Hi! Feetness is in private beta. Ask the person who invited you for a link, then tap it to connect.';

    public const string LINK_FAILED = "That link didn't work. It may have expired or been used already, so ask for a new one.";

    public const string CONNECTED_ELSEWHERE = 'This Telegram account is already connected to another Feetness profile, so that link was not used. Ask the person who invited you to check.';

    private const int MAX_LINK_ATTEMPTS = 5;

    /** "/start CODE" from an invite link, or "/link CODE" typed by hand. */
    private const string START_WITH_CODE = '/^\/(?:start|link)(?:@\w+)?\s+([A-Za-z0-9_-]{6,64})\s*$/';

    public function __construct(
        private ChatCommands $commands,
        private AnswerChain $answers,
        private RespondToText $respond,
        private KeepMessage $keep,
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

        if (preg_match(self::START_WITH_CODE, $text, $m)) {
            return $this->linkWhileConnected($user, $m[1]);
        }

        if (str_starts_with($text, '/')) {
            $command = $this->commands->find((string) strtok($text, ' @'));

            if ($command !== null) {
                return $command->reply($user);
            }
        }

        return $this->freeText($user, $message, $text);
    }

    private function freeText(User $user, InboundMessage $message, string $text): string
    {
        $answer = $this->answers->handle($user, $text);

        return match (true) {
            $answer === null => $this->respondWithAi($user, $message, $text),
            $answer->followUp === null => $answer->reply,
            default => $answer->reply."\n\n".$this->respondWithAi($user, $message, $answer->followUp),
        };
    }

    /** Needs the AI; when it is down the message is kept and answered later. */
    private function respondWithAi(User $user, InboundMessage $message, string $text): string
    {
        $this->keep->markResent($user, $text);

        try {
            return $this->respond->reply($user, $text, $message->provider->logSource());
        } catch (AiUnavailable) {
            $this->keep->handle($user, $message, $text);

            return KeepMessage::REPLY;
        }
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

            return "Welcome, {$user->name}! Your account is connected.\n\n".HelpCommand::TEXT;
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

        return "You're already connected, {$user->name}.\n\n".HelpCommand::TEXT;
    }
}
