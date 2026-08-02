<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Tests\Fakes\FakeSmartLogParser;

it('persists a parsed workout end-to-end with a faked parser', function (): void {
    Queue::fake();
    fakeSmartLogParser(FakeSmartLogParser::workout());

    $user = User::factory()->create([
        'rpg_strength' => 10, 'rpg_stamina' => 10, 'rpg_vitality' => 10,
    ]);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/log', ['message' => 'Benched 100kg 3x5 today'])
        ->assertCreated()
        ->assertJsonPath('log_type', 'workout')
        ->assertJsonPath('rpg.strength', 12);

    $this->assertDatabaseHas('workout_sessions', ['user_id' => $user->id, 'duration_minutes' => 45]);
    $this->assertDatabaseHas('exercise_entries', ['exercise_name' => 'Bench Press', 'weight_kg' => 100]);
    $this->assertDatabaseHas('activity_feedbacks', ['user_id' => $user->id, 'lt_surge' => 'Solid pressing, Soldier.']);
    $this->assertDatabaseHas('diary_entries', ['user_id' => $user->id, 'content' => 'Benched 100kg for 3 sets of 5.']);
    $this->assertDatabaseHas('custom_rpg_stats', ['user_id' => $user->id, 'name' => 'Bench Press Peak']);
});

it('returns 503 when the parser port fails', function (): void {
    fakeSmartLogParser(FakeSmartLogParser::workout()->failing());

    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/log', ['message' => 'Benched 100kg today'])
        ->assertServiceUnavailable();

    $this->assertDatabaseCount('workout_sessions', 0);
});
