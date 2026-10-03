<?php

declare(strict_types=1);

use App\Enums\TrainerPersona;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Tests\Fakes\FakeSmartLogParser;
use Tests\Fakes\FakeTrainerChat;

it('smart-log outage is logged, not swallowed', function (): void {
    fakeSmartLogParser(FakeSmartLogParser::workout()->failing());
    Log::spy();

    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/log', ['message' => 'Benched 100kg today'])
        ->assertStatus(503);

    $this->assertDatabaseCount('workout_sessions', 0);

    Log::shouldHaveReceived('error')
        ->withArgs(fn (string $message, array $context = []): bool => $message === 'AI call failed'
            && ! str_contains(json_encode($context), 'Benched 100kg today'))
        ->once();
});

it('chat 503 keeps the coach key', function (): void {
    fakeTrainerChat((new FakeTrainerChat)->failing());

    $user = User::factory()->asLatika()->create();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/trainer/chat', ['message' => 'anyone?'])
        ->assertStatus(503)
        ->assertJson([
            'reply' => TrainerPersona::Latika->downMessage(),
            'conversation_id' => null,
            'coach' => 'latika',
        ]);
});
