<?php

declare(strict_types=1);

namespace App\Channels\Telegram;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * The Telegram Bot API calls we use: getUpdates (long polling), sendMessage,
 * getMe (the bot's username for invite links) and setMyCommands (the menu).
 */
final readonly class TelegramClient
{
    private const int MAX_TEXT = 4000;

    public function __construct(
        private string $token,
    ) {}

    /**
     * @return list<array<string, mixed>>
     *
     * @throws TelegramUnavailable
     */
    public function getUpdates(?int $offset, int $timeout = 30): array
    {
        $body = $this->call('getUpdates', [
            'offset' => $offset,
            'timeout' => $timeout,
            'allowed_updates' => ['message'],
        ], $timeout + 10);

        return array_values($body['result'] ?? []);
    }

    /**
     * @throws TelegramUnavailable
     */
    public function sendMessage(string $chatId, string $text): void
    {
        $this->call('sendMessage', [
            'chat_id' => $chatId,
            'text' => mb_substr($text, 0, self::MAX_TEXT),
        ], 10);
    }

    /**
     * @return array<string, mixed> the bot's own user (id, username, first_name)
     *
     * @throws TelegramUnavailable
     */
    public function getMe(): array
    {
        return $this->call('getMe', [], 10)['result'] ?? [];
    }

    /**
     * @param  list<array{command: string, description: string}>  $commands
     *
     * @throws TelegramUnavailable
     */
    public function setMyCommands(array $commands): void
    {
        $this->call('setMyCommands', ['commands' => $commands], 10);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     *
     * @throws TelegramUnavailable
     */
    private function call(string $method, array $payload, int $timeout): array
    {
        try {
            $response = Http::timeout($timeout)
                ->asJson()
                ->post("https://api.telegram.org/bot{$this->token}/{$method}", array_filter($payload, static fn (mixed $v): bool => $v !== null));
        } catch (ConnectionException) {
            throw new TelegramUnavailable("Telegram {$method}: connection failed");
        }

        if ($response->failed()) {
            throw new TelegramUnavailable(sprintf(
                'Telegram %s: HTTP %d %s',
                $method,
                $response->status(),
                (string) $response->json('description', ''),
            ));
        }

        return $response->json() ?? [];
    }
}
