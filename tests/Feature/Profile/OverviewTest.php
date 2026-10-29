<?php

declare(strict_types=1);

use App\Actions\SmartLog\RecordSmartLog;
use App\Models\User;

it('gives the app everything the home screen shows', function (): void {
    $user = User::factory()->create(['name' => 'Hiren', 'training_days_per_week' => 3]);
    app(RecordSmartLog::class)->handle($user, "Yesterday did squats 5x5\n90 80 80 80 80", workoutFacts([
        ['exercise_name' => 'Squat', 'sets' => 5, 'reps' => 5, 'set_list' => [
            ['reps' => 5, 'weight_kg' => 90.0], ['reps' => 5, 'weight_kg' => 80.0], ['reps' => 5, 'weight_kg' => 80.0], ['reps' => 5, 'weight_kg' => 80.0], ['reps' => 5, 'weight_kg' => 80.0],
        ]],
    ], ['logged_on' => today()->subDay()->toDateString()]));
    $user->profileVersions()->create(['kind' => 'rolling', 'notes' => [['text' => 'Squats most weeks.', 'cites' => ['facts']]]]);

    $this->actingAs($user, 'sanctum')->getJson('/api/me/overview')
        ->assertOk()
        ->assertJsonPath('user.name', 'Hiren')
        ->assertJsonPath('facts.entered_by_user.training_days_per_week', 3)
        ->assertJsonPath('facts.this_week.sessions', 1)
        ->assertJsonPath('notes.items.0.text', 'Squats most weeks.')
        ->assertJsonPath('recent_logs.0.message', "Yesterday did squats 5x5\n90 80 80 80 80")
        ->assertJsonPath('recent_logs.0.entries.0.exercise_name', 'Squat')
        ->assertJsonPath('recent_logs.0.entries.0.set_list.0.weight_kg', 90)
        ->assertJsonPath('recent_logs.0.entries.0.set_list.1.weight_kg', 80)
        ->assertJsonPath('recent_logs.0.entries.0.volume_kg', 2050)
        ->assertJsonPath('recent_logs.0.logged_on', today()->subDay()->toDateString());
});

it('pages the message history and keeps users apart', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();
    app(RecordSmartLog::class)->handle($user, 'bench', workoutFacts([lift('Bench Press')]));
    app(RecordSmartLog::class)->handle($other, 'squat', workoutFacts([lift('Squat')]));

    $this->actingAs($user, 'sanctum')->getJson('/api/logs')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.entries.0.exercise_name', 'Bench Press');
});
