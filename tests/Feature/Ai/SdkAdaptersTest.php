<?php

declare(strict_types=1);

use App\Ai\Agents\PlanAgent;
use App\Ai\Agents\SmartLogAgent;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Ai;
use Tests\Fakes\FakeSmartLogParser;

/*
 * These tests exercise the REAL SDK adapters (not the port fakes) with the
 * SDK's own agent fake, so a wrong call on the agent object fails here.
 */

function smartLogPayload(array $overrides = []): array
{
    $fake = FakeSmartLogParser::workout();

    return array_merge($fake->parse(User::factory()->make(), 'x'), $overrides);
}

it('smart log persists through the real SdkSmartLogParser', function (): void {
    Queue::fake();
    Ai::fakeAgent(SmartLogAgent::class, [smartLogPayload()]);

    $user = User::factory()->create(['rpg_strength' => 10, 'rpg_stamina' => 10, 'rpg_vitality' => 10]);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/log', ['message' => 'Benched 100kg 3x5'])
        ->assertCreated()
        ->assertJsonPath('log_type', 'workout')
        ->assertJsonPath('rpg.strength', 12);

    $this->assertDatabaseHas('exercise_entries', ['exercise_name' => 'Bench Press']);
});

it('workout plan generates through the real SdkPlanGenerator', function (): void {
    Ai::fakeAgent(PlanAgent::class, ['# Shen weekly programme']);

    $user = User::factory()->asShen()->withCompleteProfile()->create();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/plans/workout')
        ->assertOk()
        ->assertJsonPath('plan', '# Shen weekly programme');

    $this->assertDatabaseCount('agent_conversations', 0);
});

it('junk payload is a 503 before any write', function (): void {
    Ai::fakeAgent(SmartLogAgent::class, [['nonsense' => true]]);

    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/log', ['message' => 'Benched 100kg 3x5'])
        ->assertStatus(503);

    $this->assertDatabaseCount('activity_feedbacks', 0);
    $this->assertDatabaseCount('workout_sessions', 0);
});

it('long stat name is truncated to 60 characters', function (): void {
    Queue::fake();
    Ai::fakeAgent(SmartLogAgent::class, [smartLogPayload(['rpg_stat_name' => str_repeat('x', 300)])]);

    $user = User::factory()->create(['rpg_strength' => 10, 'rpg_stamina' => 10, 'rpg_vitality' => 10]);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/log', ['message' => 'Benched 100kg 3x5'])
        ->assertCreated();

    expect(strlen((string) $user->customRpgStats()->first()->name))->toBe(60);
});
