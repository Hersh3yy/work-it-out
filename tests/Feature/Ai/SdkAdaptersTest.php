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

    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/log', ['message' => 'Benched 100kg 3x5'])
        ->assertCreated()
        ->assertJsonPath('log_type', 'workout');

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
    Ai::fakeAgent(SmartLogAgent::class, [['nonsense' => true], ['nonsense' => true]]);

    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/log', ['message' => 'Benched 100kg 3x5'])
        ->assertStatus(503);

    $this->assertDatabaseCount('activity_logs', 0);
    $this->assertDatabaseCount('workout_sessions', 0);
});

it('retries once when the answer does not fit the form', function (): void {
    Queue::fake();
    Ai::fakeAgent(SmartLogAgent::class, [['nonsense' => true], smartLogPayload()]);

    $this->actingAs(User::factory()->create(), 'sanctum')
        ->postJson('/api/log', ['message' => 'Benched 100kg 3x5'])
        ->assertCreated();
});

it('drops anything beyond facts and coerces types', function (): void {
    Queue::fake();
    Ai::fakeAgent(SmartLogAgent::class, [smartLogPayload([
        'lt_surge_feedback' => 'Solid pressing, Soldier.',
        'rpg_strength_delta' => 5,
        'rpg_stat_name' => 'Bench Press Peak',
        'exercises' => [['exercise_name' => str_repeat('x', 300), 'sets' => '3', 'reps' => 5.0, 'weight_kg' => '80']],
        'questions' => [
            ['field' => 'exercises.0.reps', 'question' => 'How many reps?'],
            ['field' => 'exercises.0.sets', 'question' => 'How many sets?'],
        ],
    ])]);

    $user = User::factory()->create(['rpg_strength' => 10]);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/log', ['message' => 'Benched 80kg 3x5'])
        ->assertCreated()
        ->assertJsonPath('entries.0.sets', 3)
        ->assertJsonPath('entries.0.weight_kg', 80)
        ->assertJsonCount(1, 'questions');

    $this->assertDatabaseCount('custom_rpg_stats', 0);
    expect(mb_strlen((string) $user->workoutSessions()->first()->exerciseEntries()->first()->exercise_name))->toBe(100);
});
