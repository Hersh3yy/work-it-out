<?php

declare(strict_types=1);

use App\Models\User;

/*
 * The app signs in only with the one-time link from /login in Telegram
 * (MagicLoginTest); these guard the door around it.
 */

function wrongSignInLink(): array
{
    return ['token' => str_repeat('a', 32)];
}

it('logs out and revokes the token', function (): void {
    $user = User::factory()->create();
    $newToken = $user->createToken('test');

    $this->withToken($newToken->plainTextToken)
        ->postJson('/api/auth/logout')
        ->assertOk();

    $this->assertDatabaseMissing('personal_access_tokens', ['id' => $newToken->accessToken->id]);
});

it('limits sign-in attempts to five a minute', function (): void {
    foreach (range(1, 5) as $try) {
        $this->postJson('/api/auth/magic', wrongSignInLink())->assertUnauthorized();
    }

    $this->postJson('/api/auth/magic', wrongSignInLink())->assertStatus(429);
});

it('ignores a spoofed X-Forwarded-For from a public address', function (): void {
    foreach (range(1, 5) as $try) {
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])
            ->withHeader('X-Forwarded-For', "198.51.100.{$try}")
            ->postJson('/api/auth/magic', wrongSignInLink());
    }

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])
        ->withHeader('X-Forwarded-For', '198.51.100.99')
        ->postJson('/api/auth/magic', wrongSignInLink())
        ->assertStatus(429);
});

it('has no password sign-up or sign-in', function (string $path): void {
    $this->postJson($path, ['email' => 'someone@example.com', 'password' => 'password123'])->assertNotFound();
})->with(['/api/auth/register', '/api/auth/login']);
