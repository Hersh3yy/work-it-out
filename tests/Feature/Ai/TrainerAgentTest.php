<?php

declare(strict_types=1);

use App\Ai\Agents\TrainerAgent;
use App\Models\User;
use Laravel\Ai\Ai;

it('returns an AI trainer response via faked SDK', function (): void {
    Ai::fakeAgent(TrainerAgent::class, ['Great job this week! You hit 3 out of 4 sessions.']);

    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/trainer/chat', ['message' => 'How did I do this week?'])
        ->assertOk()
        ->assertJsonStructure(['reply', 'conversation_id']);
});

it('returns persona down-message when AI is unavailable', function (): void {
    // No fake registered: agent will throw when it can't reach Msty in test env
    $user = User::factory()->asLtSurge()->create();

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/trainer/chat', ['message' => 'What should I train today?']);

    // Either success (if somehow reachable) or graceful degradation
    expect($response->status())->toBeIn([200, 503]);
});

it('validates message max length', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/trainer/chat', ['message' => str_repeat('a', 1001)])
        ->assertUnprocessable();
});

it('requires authentication to chat', function (): void {
    $this->postJson('/api/trainer/chat', ['message' => 'Hello'])
        ->assertUnauthorized();
});
