<?php

declare(strict_types=1);

use App\Models\CustomRpgStat;
use App\Models\ExerciseEntry;
use App\Models\User;
use App\Models\WorkoutSession;

it('returns computed records, rpg snapshot, and custom stats', function (): void {
    $user = User::factory()->create([
        'rpg_strength' => 42, 'rpg_stamina' => 17, 'rpg_vitality' => 9,
    ]);

    $session = WorkoutSession::factory()->for($user)->create(['logged_at' => now()->subDay()]);
    ExerciseEntry::factory()->for($session, 'workoutSession')->create([
        'exercise_name' => 'Bench Press', 'sets' => 3, 'reps' => 5, 'weight_kg' => 100,
    ]);

    CustomRpgStat::create([
        'user_id' => $user->id, 'name' => 'Bench Press Peak', 'value' => 55,
        'level' => 2, 'unit' => 'score', 'category' => 'strength',
        'last_updated_at' => now(),
    ]);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/stats')
        ->assertOk()
        ->assertJsonPath('records.strength.0.exercise', 'Bench Press')
        ->assertJsonPath('records.strength.0.max_weight_kg', fn (int|float $kg): bool => (float) $kg === 100.0)
        ->assertJsonPath('rpg.strength', 42)
        ->assertJsonPath('custom_stats.0.name', 'Bench Press Peak')
        ->assertJsonStructure([
            'records' => ['strength', 'endurance' => ['longest_distance', 'best_pace'], 'sports'],
            'rpg' => ['strength', 'stamina', 'vitality'],
            'custom_stats',
        ]);
});

it('requires authentication', function (): void {
    $this->getJson('/api/stats')->assertUnauthorized();
});
