<?php

declare(strict_types=1);

use App\Models\User;

it('registers a new user and returns a token', function (): void {
    $response = $this->postJson('/api/auth/register', [
        'name'                  => 'Ruby Test',
        'email'                 => 'ruby@test.com',
        'password'              => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertCreated()
        ->assertJsonStructure(['user', 'token'])
        ->assertJsonPath('user.email', 'ruby@test.com');
});

it('logs in with valid credentials and returns a token', function (): void {
    $user = User::factory()->create(['password' => 'password']);

    $response = $this->postJson('/api/auth/login', [
        'email'    => $user->email,
        'password' => 'password',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['user', 'token']);
});

it('returns 401 for invalid login credentials', function (): void {
    User::factory()->create(['email' => 'test@example.com']);

    $this->postJson('/api/auth/login', [
        'email'    => 'test@example.com',
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
    $user       = User::factory()->create();
    $newToken   = $user->createToken('test');
    $plainText  = $newToken->plainTextToken;

    $this->withToken($plainText)
        ->postJson('/api/auth/logout')
        ->assertOk();

    // The personal access token record must be gone from the database.
    $this->assertDatabaseMissing('personal_access_tokens', [
        'id' => $newToken->accessToken->id,
    ]);
});
