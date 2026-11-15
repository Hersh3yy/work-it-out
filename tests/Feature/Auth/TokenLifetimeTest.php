<?php

declare(strict_types=1);

use App\Models\User;

/*
 * App tokens end (audit 2026-11-08 tier 1 item 10): they expire, and
 * unlinking someone's chat signs the app out too.
 */

it('stops accepting an app token after 30 days', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('feetness-app')->plainTextToken;

    $this->withToken($token)->getJson('/api/me/overview')->assertOk();
    $this->app['auth']->forgetGuards();
    $this->travel(31)->days();

    $this->withToken($token)->getJson('/api/me/overview')->assertUnauthorized();
});

it('signs the app out when the chat account is unlinked', function (): void {
    $user = linkedUser();
    $user->createToken('feetness-app');

    $this->artisan('channel:unlink', ['email' => $user->email])->assertSuccessful();

    expect($user->tokens()->count())->toBe(0);
});
