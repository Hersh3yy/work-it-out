<?php

declare(strict_types=1);

use App\Channels\Telegram\TelegramChannel;
use App\Channels\Telegram\TelegramClient;
use App\Exceptions\ChatUnavailable;
use App\Exceptions\RecipientUnreachable;
use Illuminate\Support\Facades\Http;

/*
 * The core only knows the port's two failures: the person blocked the bot
 * (stop sending) or Telegram did not take it this time.
 */

function telegramAnswers(int $status, string $description): TelegramChannel
{
    Http::fake(['*/sendMessage' => Http::response(['ok' => false, 'description' => $description], $status)]);

    return new TelegramChannel(new TelegramClient('test-token'));
}

it('reads a blocked bot as an unreachable person', function (): void {
    telegramAnswers(403, 'Forbidden: bot was blocked by the user')->send('4242', 'Leg day.');
})->throws(RecipientUnreachable::class);

it('reads any other refusal as Telegram being unavailable this time', function (): void {
    $error = rescue(fn () => telegramAnswers(502, 'Bad Gateway')->send('4242', 'Leg day.'), fn (Throwable $e) => $e, report: false);

    expect($error)->toBeInstanceOf(ChatUnavailable::class)
        ->not->toBeInstanceOf(RecipientUnreachable::class);
});
