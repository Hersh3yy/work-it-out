<?php

declare(strict_types=1);

use App\Models\ChannelIdentity;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Fakes\FakeSmartLogParser;

function telegramUpdate(int $id, string $text, int $from = 4242, string $chatType = 'private'): array
{
    return [
        'update_id' => $id,
        'message' => [
            'message_id' => $id,
            'from' => ['id' => $from, 'first_name' => 'Hiren'],
            'chat' => ['id' => $from, 'type' => $chatType],
            'text' => $text,
        ],
    ];
}

beforeEach(function (): void {
    config(['services.telegram.bot_token' => '123:test-token']);
});

it('polls once, logs the message and sends the receipt back', function (): void {
    fakeSmartLogParser(FakeSmartLogParser::workout());
    $user = User::factory()->create();
    ChannelIdentity::create(['user_id' => $user->id, 'provider' => 'telegram', 'external_id' => '4242']);

    Http::fake([
        '*/getUpdates' => Http::response(['ok' => true, 'result' => [
            telegramUpdate(10, 'benched 100 3x5'),
            telegramUpdate(11, 'hello group', chatType: 'group'),
        ]]),
        '*/sendMessage' => Http::response(['ok' => true]),
    ]);

    $this->artisan('channel:telegram:poll --once')->assertSuccessful();

    Http::assertSent(fn (Request $r): bool => str_ends_with($r->url(), '/sendMessage')
        && $r['chat_id'] === '4242'
        && $r['text'] === 'Logged: Bench Press 3x5 @ 100 kg');
    Http::assertSentCount(2);
    $this->assertDatabaseCount('activity_logs', 1);
    expect(cache('telegram:poll:offset'))->toBe(12);
});

it('tells the operator how to link an unknown sender and replies nothing', function (): void {
    $parser = fakeSmartLogParser();
    Http::fake([
        '*/getUpdates' => Http::response(['ok' => true, 'result' => [telegramUpdate(20, 'hi', from: 777)]]),
        '*/sendMessage' => Http::response(['ok' => true]),
    ]);

    $this->artisan('channel:telegram:poll --once')
        ->expectsOutputToContain('php artisan channel:link you@example.com 777')
        ->assertSuccessful();

    Http::assertNotSent(fn (Request $r): bool => str_ends_with($r->url(), '/sendMessage'));
    expect($parser->calls)->toBe([]);
});

it('never prints the bot token when Telegram fails', function (): void {
    Http::fake(['*/getUpdates' => Http::response(['ok' => false, 'description' => 'Unauthorized'], 401)]);

    $this->artisan('channel:telegram:poll --once')
        ->expectsOutputToContain('HTTP 401 Unauthorized')
        ->doesntExpectOutputToContain('test-token')
        ->assertFailed();
});

it('refuses to start without a token', function (): void {
    config(['services.telegram.bot_token' => null]);

    $this->artisan('channel:telegram:poll --once')->assertFailed();
});

it('links a Telegram id to a new user', function (): void {
    $this->artisan('channel:link Hiren@Example.com 4242')->assertSuccessful();

    $user = User::query()->where('email', 'hiren@example.com')->firstOrFail();
    $this->assertDatabaseHas('channel_identities', ['user_id' => $user->id, 'provider' => 'telegram', 'external_id' => '4242']);
});
