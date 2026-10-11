<?php

declare(strict_types=1);

use App\Models\User;

it('registers a new user and returns a token', function (): void {
    $response = $this->postJson('/api/auth/register', [
        'name' => 'Ruby Test',
        'email' => 'ruby@test.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertCreated()
        ->assertJsonStructure(['user', 'token'])
        ->assertJsonPath('user.email', 'ruby@test.com');
});

it('logs in with valid credentials and returns a token', function (): void {
    $user = User::factory()->create(['password' => 'password']);

    $response = $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['user', 'token']);
});

it('returns 401 for invalid login credentials', function (): void {
    User::factory()->create(['email' => 'test@example.com']);

    $this->postJson('/api/auth/login', [
        'email' => 'test@example.com',
        'password' => 'wrong-password',
    ])->assertUnauthorized();
});

it('returns the authenticated user profile', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/user')
        ->assertOk()
        ->assertJsonPath('data.email', $user->email);
});

it('logs out and revokes the token', function (): void {
    $user = User::factory()->create();
    $newToken = $user->createToken('test');
    $plainText = $newToken->plainTextToken;

    $this->withToken($plainText)
        ->postJson('/api/auth/logout')
        ->assertOk();

    // The personal access token record must be gone from the database.
    $this->assertDatabaseMissing('personal_access_tokens', [
        'id' => $newToken->accessToken->id,
    ]);
});

it('refuses registration when it is closed, as in production', function (): void {
    config(['auth.registration_open' => false]);

    $this->postJson('/api/auth/register', [
        'name' => 'Stranger',
        'email' => 'stranger@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertForbidden();

    $this->assertDatabaseMissing('users', ['email' => 'stranger@example.com']);
});

it('limits login attempts to five a minute', function (): void {
    foreach (range(1, 5) as $try) {
        $this->postJson('/api/auth/login', ['email' => 'nobody@example.com', 'password' => 'wrong-'.$try])->assertUnauthorized();
    }

    $this->postJson('/api/auth/login', ['email' => 'nobody@example.com', 'password' => 'wrong-6'])->assertStatus(429);
});

it('keeps login and sign-up limits apart', function (): void {
    foreach (range(1, 5) as $try) {
        $this->postJson('/api/auth/login', ['email' => 'nobody@example.com', 'password' => 'wrong-'.$try]);
    }

    $this->postJson('/api/auth/register', [
        'name' => 'New', 'email' => 'new@example.com', 'password' => 'password123', 'password_confirmation' => 'password123',
    ])->assertCreated();
});

it('does not reveal existing emails while registration is closed', function (): void {
    config(['auth.registration_open' => false]);
    User::factory()->create(['email' => 'taken@example.com']);

    $this->postJson('/api/auth/register', ['name' => 'x', 'email' => 'taken@example.com', 'password' => 'password123', 'password_confirmation' => 'password123'])
        ->assertForbidden()
        ->assertJsonMissingPath('errors');
});

it('ignores a spoofed X-Forwarded-For from a public address', function (): void {
    foreach (range(1, 5) as $try) {
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])
            ->withHeader('X-Forwarded-For', "198.51.100.{$try}")
            ->postJson('/api/auth/login', ['email' => 'nobody@example.com', 'password' => 'wrong']);
    }

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])
        ->withHeader('X-Forwarded-For', '198.51.100.99')
        ->postJson('/api/auth/login', ['email' => 'nobody@example.com', 'password' => 'wrong'])
        ->assertStatus(429);
});
