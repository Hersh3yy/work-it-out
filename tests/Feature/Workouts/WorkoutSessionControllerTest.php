<?php

declare(strict_types=1);

use App\Jobs\UpdateUserStats;
use App\Models\ExerciseEntry;
use App\Models\User;
use App\Models\WorkoutSession;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    Queue::fake();
});

it('stores a workout session with nested exercise entries in a transaction', function (): void {
    $user = User::factory()->create();

    $payload = [
        'logged_at'          => now()->toIso8601String(),
        'duration_minutes'   => 60,
        'perceived_exertion' => 7,
        'energy_level'       => 4,
        'completed_planned'  => true,
        'exercises'          => [
            ['exercise_name' => 'Barbell Squat', 'sets' => 5, 'reps' => 5, 'weight_kg' => 100.0],
            ['exercise_name' => 'Deadlift',      'sets' => 4, 'reps' => 3, 'weight_kg' => 120.0],
        ],
    ];

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/workouts', $payload);

    $response->assertCreated()
        ->assertJsonStructure([
            'data' => ['id', 'logged_at', 'duration_minutes', 'exercises'],
        ]);

    $this->assertDatabaseHas('workout_sessions', ['user_id' => $user->id]);
    $this->assertDatabaseCount('exercise_entries', 2);

    Queue::assertPushed(UpdateUserStats::class);
});

it('returns only the authenticated user sessions', function (): void {
    $user  = User::factory()->create();
    $other = User::factory()->create();

    WorkoutSession::factory()->for($user)->count(3)->create();
    WorkoutSession::factory()->for($other)->count(2)->create();

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/workouts')
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

it('soft-deletes a workout session', function (): void {
    $user    = User::factory()->create();
    $session = WorkoutSession::factory()->for($user)->create();

    $this->actingAs($user, 'sanctum')
        ->deleteJson("/api/workouts/{$session->id}")
        ->assertOk();

    $this->assertSoftDeleted('workout_sessions', ['id' => $session->id]);
});

it('cannot access another user workout session', function (): void {
    $user    = User::factory()->create();
    $other   = User::factory()->create();
    $session = WorkoutSession::factory()->for($other)->create();

    $this->actingAs($user, 'sanctum')
        ->getJson("/api/workouts/{$session->id}")
        ->assertNotFound();
});
