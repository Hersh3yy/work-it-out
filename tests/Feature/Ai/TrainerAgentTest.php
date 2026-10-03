<?php

declare(strict_types=1);

use App\Ai\Agents\TrainerAgent;
use App\Enums\TrainerPersona;
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
    Ai::fakeAgent(TrainerAgent::class, fn () => throw new RuntimeException('provider down'));

    $user = User::factory()->asLtSurge()->create();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/trainer/chat', ['message' => 'What should I train today?'])
        ->assertStatus(503)
        ->assertJson([
            'reply' => TrainerPersona::LtSurge->downMessage(),
            'conversation_id' => null,
            'coach' => 'lt_surge',
        ]);
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
