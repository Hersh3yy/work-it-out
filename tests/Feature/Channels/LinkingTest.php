<?php

declare(strict_types=1);

use App\Actions\Linking\IssueLinkCode;
use App\Channels\HandleInboundMessage;
use App\Models\ChannelIdentity;
use App\Models\ChannelLinkCode;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

function inviteCodeFor(User $user): string
{
    return app(IssueLinkCode::class)->handle($user)['code'];
}

it('links a Telegram account through a one-time invite code', function (): void {
    $user = User::factory()->create(['name' => 'Sam']);
    $code = inviteCodeFor($user);

    $reply = app(HandleInboundMessage::class)->handle(telegramText("/start {$code}", '777'));

    expect($reply)->toStartWith('Welcome, Sam! Your account is connected.');
    $this->assertDatabaseHas('channel_identities', ['user_id' => $user->id, 'provider' => 'telegram', 'external_id' => '777']);
    expect(ChannelLinkCode::query()->first()->used_at)->not->toBeNull();
});

it('stores only a hash of the code', function (): void {
    $code = inviteCodeFor(User::factory()->create());

    $this->assertDatabaseMissing('channel_link_codes', ['code_hash' => $code]);
    $this->assertDatabaseHas('channel_link_codes', ['code_hash' => hash('sha256', $code)]);
});

it('accepts /link as well as /start', function (): void {
    $user = User::factory()->create();

    expect(app(HandleInboundMessage::class)->handle(telegramText('/link '.inviteCodeFor($user), '777')))->toStartWith('Welcome');
});

it('refuses a used, expired or unknown code', function (): void {
    $user = User::factory()->create();
    $chat = app(HandleInboundMessage::class);

    $used = inviteCodeFor($user);
    $chat->handle(telegramText("/start {$used}", '777'));
    expect($chat->handle(telegramText("/start {$used}", '888')))->toBe(HandleInboundMessage::LINK_FAILED);

    $expired = inviteCodeFor($user);
    $this->travel(8)->days();
    expect($chat->handle(telegramText("/start {$expired}", '999')))->toBe(HandleInboundMessage::LINK_FAILED);

    expect($chat->handle(telegramText('/start NOPE123456', '555')))->toBe(HandleInboundMessage::LINK_FAILED);
    $this->assertDatabaseCount('channel_identities', 1);
});

it('never moves an account already linked to someone else', function (): void {
    $first = User::factory()->create();
    $second = User::factory()->create();
    ChannelIdentity::create(['user_id' => $first->id, 'provider' => 'telegram', 'external_id' => '777']);

    $reply = app(HandleInboundMessage::class)->handle(telegramText('/start '.inviteCodeFor($second), '777'));

    expect($reply)->not->toStartWith('Welcome');
    $this->assertDatabaseHas('channel_identities', ['external_id' => '777', 'user_id' => $first->id]);
});

it('goes quiet after five wrong codes in an hour', function (): void {
    $chat = app(HandleInboundMessage::class);

    foreach (range(1, 5) as $try) {
        expect($chat->handle(telegramText("/start WRONGCODE{$try}", '555')))->toBe(HandleInboundMessage::LINK_FAILED);
    }

    expect($chat->handle(telegramText('/start WRONGCODE6', '555')))->toBeNull();
});

it('gives a stranger one polite reply a day and never parses their message', function (): void {
    $parser = fakeSmartLogParser();
    $chat = app(HandleInboundMessage::class);

    expect($chat->handle(telegramText('bench 3x5 100', '555')))->toBe(HandleInboundMessage::STRANGER)
        ->and($chat->handle(telegramText('hello?', '555')))->toBeNull()
        ->and($parser->calls)->toBe([]);

    $this->travel(25)->hours();
    expect($chat->handle(telegramText('hello again', '555')))->toBe(HandleInboundMessage::STRANGER);
});

it('prints an invite link with the bot username', function (): void {
    config(['services.telegram.bot_token' => '123:test-token']);
    Http::fake(['*/getMe' => Http::response(['ok' => true, 'result' => ['id' => 1, 'username' => 'FeetnessBot']])]);

    expect(Artisan::call('channel:invite', ['email' => 'Sam@Example.com', '--name' => 'Sam']))->toBe(0)
        ->and(Artisan::output())->toContain('https://t.me/FeetnessBot?start=');

    $this->assertDatabaseHas('users', ['email' => 'sam@example.com', 'name' => 'Sam']);
    $this->assertDatabaseCount('channel_link_codes', 1);
});

it('prints the code itself when there is no bot token', function (): void {
    config(['services.telegram.bot_token' => null]);

    expect(Artisan::call('channel:invite', ['email' => 'sam@example.com']))->toBe(0)
        ->and(Artisan::output())->toContain('/start ');
});

it('sets the bot command menu', function (): void {
    config(['services.telegram.bot_token' => '123:test-token']);
    Http::fake([
        '*/getMe' => Http::response(['ok' => true, 'result' => ['id' => 1, 'username' => 'FeetnessBot']]),
        '*/setMyCommands' => Http::response(['ok' => true, 'result' => true]),
    ]);

    $this->artisan('channel:telegram:setup')->expectsOutputToContain('@FeetnessBot is ready')->assertSuccessful();

    Http::assertSent(fn (Request $r): bool => str_ends_with($r->url(), '/setMyCommands')
        && array_column($r['commands'], 'command') === ['help', 'undo']);
});
