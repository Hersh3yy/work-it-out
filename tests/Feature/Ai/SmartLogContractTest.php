<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Tests\Fakes\FakeSmartLogParser;

it('persists a parsed workout end-to-end and returns a receipt', function (): void {
    Queue::fake();
    fakeSmartLogParser(FakeSmartLogParser::workout());

    $user = User::factory()->create([
        'rpg_strength' => 10, 'rpg_stamina' => 10, 'rpg_vitality' => 10,
    ]);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/log', ['message' => 'Benched 100kg 3x5 today'])
        ->assertCreated()
        ->assertJsonPath('log_type', 'workout')
        ->assertJsonPath('summary', 'Bench Press 3x5 @ 100 kg')
        ->assertJsonPath('logged_on', today()->toDateString())
        ->assertJsonPath('added_to_existing', false)
        ->assertJsonPath('entries.0.exercise_name', 'Bench Press')
        ->assertJsonPath('entries.0.weight_kg', 100)
        ->assertJsonPath('questions', [])
        ->assertJsonPath('diary.content', 'Bench Press 3x5 @ 100 kg')
        ->assertJsonPath('rpg.strength', 10)
        ->assertJsonMissingPath('feedback');

    $this->assertDatabaseHas('workout_sessions', ['user_id' => $user->id, 'duration_minutes' => 45, 'completed_planned' => true]);
    $this->assertDatabaseHas('exercise_entries', ['exercise_name' => 'Bench Press', 'weight_kg' => 100]);
    $this->assertDatabaseHas('activity_logs', ['user_id' => $user->id, 'source' => 'http', 'log_type' => 'workout']);
    $this->assertDatabaseCount('custom_rpg_stats', 0);
});

it('returns the question when a value is missing', function (): void {
    Queue::fake();
    fakeSmartLogParser(FakeSmartLogParser::missingWeight());

    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/log', ['message' => 'incline db press 3x10'])
        ->assertCreated()
        ->assertJsonPath('entries.0.weight_kg', null)
        ->assertJsonPath('questions.0.field', 'exercises.0.weight_kg')
        ->assertJsonPath('questions.0.question', 'What weight for the incline press?');
});

it('returns 503 when the parser port fails', function (): void {
    fakeSmartLogParser(FakeSmartLogParser::workout()->failing());

    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/log', ['message' => 'Benched 100kg today'])
        ->assertServiceUnavailable();

    $this->assertDatabaseCount('workout_sessions', 0);
    $this->assertDatabaseCount('activity_logs', 0);
});
