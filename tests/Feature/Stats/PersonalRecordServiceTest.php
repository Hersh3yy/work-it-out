<?php

declare(strict_types=1);

use App\Contracts\Stats\PersonalRecords;
use App\Models\ExerciseEntry;
use App\Models\User;
use App\Models\WorkoutSession;

it('computes the max bench press from real exercise entries', function (): void {
    $user = User::factory()->create();

    $older = WorkoutSession::factory()->for($user)->create(['logged_at' => now()->subDays(20)]);
    $newer = WorkoutSession::factory()->for($user)->create(['logged_at' => now()->subDays(2)]);

    ExerciseEntry::factory()->for($older, 'workoutSession')->create([
        'exercise_name' => 'Bench Press', 'sets' => 3, 'reps' => 8, 'weight_kg' => 80,
    ]);
    ExerciseEntry::factory()->for($newer, 'workoutSession')->create([
        'exercise_name' => 'bench press', 'sets' => 3, 'reps' => 5, 'weight_kg' => 100,
    ]);

    $records = app(PersonalRecords::class)->for($user);

    expect($records['strength'])->toHaveCount(1)
        ->and($records['strength'][0]['exercise'])->toBe('bench press')
        ->and($records['strength'][0]['max_weight_kg'])->toBe(100.0)
        ->and($records['strength'][0]['reps'])->toBe(5)
        ->and($records['strength'][0]['achieved_on'])->toBe(now()->subDays(2)->toDateString());
});

it('computes best run distance and pace', function (): void {
    $user = User::factory()->create();
    $session = WorkoutSession::factory()->for($user)->create(['logged_at' => now()->subDay()]);

    // 5 km in 25:00 → 5:00 /km
    ExerciseEntry::factory()->for($session, 'workoutSession')->create([
        'exercise_name' => 'Treadmill Run',
        'sets' => null, 'reps' => null, 'weight_kg' => null,
        'duration_seconds' => 1500,
        'distance_meters' => 5000,
    ]);

    // 8 km in 48:00 → 6:00 /km (longer but slower)
    ExerciseEntry::factory()->for($session, 'workoutSession')->create([
        'exercise_name' => 'Outdoor Run',
        'sets' => null, 'reps' => null, 'weight_kg' => null,
        'duration_seconds' => 2880,
        'distance_meters' => 8000,
    ]);

    $records = app(PersonalRecords::class)->for($user);

    expect($records['endurance']['longest_distance']['distance_meters'])->toBe(8000.0)
        ->and($records['endurance']['best_pace']['exercise'])->toBe('Treadmill Run')
        ->and($records['endurance']['best_pace']['seconds_per_km'])->toBe(300)
        ->and($records['endurance']['best_pace']['pace_label'])->toBe('5:00 /km');
});

it('tracks padel session history', function (): void {
    $user = User::factory()->create();

    $first = WorkoutSession::factory()->for($user)->create([
        'logged_at' => now()->subDays(10), 'duration_minutes' => 60,
    ]);
    $second = WorkoutSession::factory()->for($user)->create([
        'logged_at' => now()->subDays(3), 'duration_minutes' => 90,
    ]);

    foreach ([$first, $second] as $session) {
        ExerciseEntry::factory()->for($session, 'workoutSession')->create([
            'exercise_name' => 'Padel',
            'sets' => null, 'reps' => null, 'weight_kg' => null,
        ]);
    }

    $records = app(PersonalRecords::class)->for($user);

    expect($records['sports'])->toHaveCount(1)
        ->and($records['sports'][0]['name'])->toBe('Padel')
        ->and($records['sports'][0]['sessions'])->toBe(2)
        ->and($records['sports'][0]['longest_duration_minutes'])->toBe(90)
        ->and($records['sports'][0]['last_played_on'])->toBe(now()->subDays(3)->toDateString());
});

it('returns empty records for a user with no workouts', function (): void {
    $user = User::factory()->create();

    $records = app(PersonalRecords::class)->for($user);

    expect($records['strength'])->toBe([])
        ->and($records['endurance']['longest_distance'])->toBeNull()
        ->and($records['endurance']['best_pace'])->toBeNull()
        ->and($records['sports'])->toBe([]);
});

it('never leaks another user\'s records', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $session = WorkoutSession::factory()->for($other)->create();
    ExerciseEntry::factory()->for($session, 'workoutSession')->create([
        'exercise_name' => 'Bench Press', 'weight_kg' => 200,
    ]);

    expect(app(PersonalRecords::class)->for($user)['strength'])->toBe([]);
});
