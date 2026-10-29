<?php

declare(strict_types=1);

use App\Actions\Auth\IssueLoginLink;
use App\Channels\HandleInboundMessage;
use App\Models\LoginCode;
use App\Models\User;

function tokenFromLink(string $link): string
{
    parse_str((string) parse_url($link, PHP_URL_QUERY), $query);

    return (string) $query['token'];
}

it('hands out a sign-in link over chat that logs into the app once', function (): void {
    config(['app.web_url' => 'https://app.feetness.test']);
    $user = linkedUser();

    $reply = app(HandleInboundMessage::class)->handle(telegramText('/login'));

    expect($reply)->toContain('https://app.feetness.test/login?token=');
    preg_match('#(https://\S+)#', $reply, $m);
    $token = tokenFromLink($m[1]);

    $this->postJson('/api/auth/magic', ['token' => $token])
        ->assertOk()
        ->assertJsonPath('user.id', $user->id)
        ->assertJsonStructure(['token']);

    $this->postJson('/api/auth/magic', ['token' => $token])->assertUnauthorized();
});

it('stores only a hash and expires after 15 minutes', function (): void {
    $user = User::factory()->create();
    $token = tokenFromLink(app(IssueLoginLink::class)->handle($user));

    $this->assertDatabaseMissing('login_codes', ['code_hash' => $token]);
    $this->assertDatabaseHas('login_codes', ['code_hash' => hash('sha256', $token)]);

    $this->travel(16)->minutes();
    $this->postJson('/api/auth/magic', ['token' => $token])->assertUnauthorized();
});

it('retires older links when a new one is made', function (): void {
    $user = User::factory()->create();
    $old = tokenFromLink(app(IssueLoginLink::class)->handle($user));
    $new = tokenFromLink(app(IssueLoginLink::class)->handle($user));

    $this->postJson('/api/auth/magic', ['token' => $old])->assertUnauthorized();
    $this->postJson('/api/auth/magic', ['token' => $new])->assertOk();
    expect(LoginCode::query()->whereNull('used_at')->where('expires_at', '>', now())->count())->toBe(0);
});

it('rejects a malformed token without touching the database', function (): void {
    $this->postJson('/api/auth/magic', ['token' => 'short'])->assertUnprocessable();
});
