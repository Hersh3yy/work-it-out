<?php

declare(strict_types=1);

use App\Actions\SmartLog\RecordSmartLog;
use App\Contracts\Stats\PersonalRecords;
use App\Models\ExerciseEntry;
use App\Models\User;
use App\Models\WorkoutSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/*
 * Hiren's superset of 2026-11-15: "10 pushups, 12 lying leg raises, 40 second
 * plank, 4 of those". Saved before this as Plank "?×?" four times.
 */
function supersetFacts(): array
{
    return workoutFacts([
        ['exercise_name' => 'Push-ups', 'sets' => 4, 'reps' => 10, 'bodyweight' => true, 'group' => 'Superset'],
        ['exercise_name' => 'Lying Leg Raise', 'sets' => 4, 'reps' => 12, 'bodyweight' => true, 'group' => 'Superset'],
        ['exercise_name' => 'Plank', 'sets' => 4, 'duration_seconds' => 40, 'bodyweight' => true, 'group' => 'Superset'],
    ]);
}

it('stores a timed set\'s seconds on every set row and totals them on the entry', function (): void {
    $user = User::factory()->create();

    $plank = app(RecordSmartLog::class)->handle($user, 'plank', supersetFacts())->entries[2];

    expect($plank->setRows()->pluck('duration_seconds')->all())->toBe([40, 40, 40, 40])
        ->and($plank->setRows()->pluck('reps')->all())->toBe([null, null, null, null])
        ->and($plank->duration_seconds)->toBe(160)
        ->and($plank->sets)->toBe(4)
        ->and($plank->bodyweight)->toBeTrue();
});

it('keeps the exercises of a superset together under one label', function (): void {
    $user = User::factory()->create();

    $entries = app(RecordSmartLog::class)->handle($user, 'superset', supersetFacts())->entries;

    expect(collect($entries)->pluck('group_label')->all())->toBe(['Superset', 'Superset', 'Superset'])
        ->and(collect($entries)->pluck('sets')->all())->toBe([4, 4, 4]);
});

it('stores the distance of each interval and totals it on the entry', function (): void {
    $user = User::factory()->create();

    $run = app(RecordSmartLog::class)->handle($user, '8x400m', workoutFacts([
        ['exercise_name' => 'Run', 'sets' => 8, 'distance_meters' => 400],
    ]))->entries[0];

    expect($run->setRows()->pluck('distance_meters')->all())->toBe(array_fill(0, 8, 400))
        ->and($run->distance_meters)->toBe(3200);
});

it('keeps a run without sets as one whole, no set rows', function (): void {
    $user = User::factory()->create();

    $run = app(RecordSmartLog::class)->handle($user, 'ran 5k in 28', workoutFacts([
        ['exercise_name' => 'Run', 'distance_meters' => 5000, 'duration_seconds' => 1680],
    ]))->entries[0];

    expect($run->setRows()->count())->toBe(0)
        ->and($run->distance_meters)->toBe(5000)
        ->and($run->duration_seconds)->toBe(1680);
});

it('stores the effort of each set, from the exercise or the set itself', function (): void {
    $user = User::factory()->create();

    [$deadlift, $bench] = app(RecordSmartLog::class)->handle($user, 'deadlift 3x5 140 rpe 8, bench', workoutFacts([
        ['exercise_name' => 'Deadlift', 'sets' => 3, 'reps' => 5, 'weight_kg' => 140.0, 'rpe' => 8.0],
        ['exercise_name' => 'Bench Press', 'set_list' => [
            ['reps' => 8, 'weight_kg' => 80.0, 'rir' => 2],
            ['reps' => 6, 'weight_kg' => 80.0, 'rir' => 0],
        ]],
    ]))->entries;

    expect($deadlift->setRows()->pluck('rpe')->map(fn ($rpe): float => (float) $rpe)->all())->toBe([8.0, 8.0, 8.0])
        ->and($bench->setRows()->pluck('rir')->all())->toBe([2, 0]);
});

it('keeps assistance as help, so it moves no volume and sets no record', function (): void {
    $user = User::factory()->create();

    $result = app(RecordSmartLog::class)->handle($user, 'assisted pull up 5x5 45kg', workoutFacts([
        ['exercise_name' => 'Assisted Pull-up', 'sets' => 5, 'reps' => 5, 'weight_kg' => 45.0, 'assisted' => true],
    ]));
    $pullUp = $result->entries[0];

    expect($pullUp->assisted)->toBeTrue()
        ->and((float) $pullUp->weight_kg)->toBe(45.0)
        ->and($pullUp->volumeKg())->toBe(0.0)
        ->and($result->session->totalVolumeKg)->toBe(0.0)
        ->and(app(PersonalRecords::class)->for($user)['strength'])->toBe([]);
});

it('adds nothing to volume or records for bodyweight and timed sets', function (): void {
    $user = User::factory()->create();

    $result = app(RecordSmartLog::class)->handle($user, 'superset', supersetFacts());

    expect($result->session->totalVolumeKg)->toBe(0.0)
        ->and(app(PersonalRecords::class)->for($user)['strength'])->toBe([]);
});

it('copies a timed entry\'s seconds onto its empty set rows once', function (): void {
    $session = WorkoutSession::factory()->create();
    $plank = ExerciseEntry::factory()->for($session, 'workoutSession')->create([
        'exercise_name' => 'Plank', 'sets' => 4, 'reps' => null, 'weight_kg' => null, 'duration_seconds' => 40,
    ]);
    $bench = ExerciseEntry::factory()->for($session, 'workoutSession')->create([
        'exercise_name' => 'Bench Press', 'sets' => 1, 'reps' => 5, 'weight_kg' => 80, 'duration_seconds' => null,
    ]);
    foreach ([$plank, $plank, $plank, $plank, $bench] as $position => $entry) {
        DB::table('exercise_sets')->insert([
            'id' => (string) Str::ulid(), 'exercise_entry_id' => $entry->id, 'position' => $position + 1,
            'reps' => $entry->reps, 'weight_kg' => $entry->weight_kg, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    $migration = require database_path('migrations/2026_11_15_000006_copy_timed_entries_onto_their_set_rows.php');
    $migration->up();
    $migration->up();

    expect($plank->setRows()->pluck('duration_seconds')->all())->toBe([40, 40, 40, 40])
        ->and($bench->setRows()->pluck('duration_seconds')->all())->toBe([null]);
});

it('tells the app which value the bot is still asking for, and only there', function (): void {
    $user = User::factory()->create();
    app(RecordSmartLog::class)->handle($user, 'pushups 3x10, incline db 3x10', workoutFacts(
        [
            ['exercise_name' => 'Push-ups', 'sets' => 3, 'reps' => 10, 'bodyweight' => true],
            ['exercise_name' => 'Incline DB Press', 'sets' => 3, 'reps' => 10],
        ],
        ['questions' => [['field' => 'exercises.1.weight_kg', 'question' => 'What weight for the incline press?']]],
    ));

    $this->actingAs($user, 'sanctum')->getJson('/api/logs')
        ->assertOk()
        ->assertJsonPath('data.0.entries.0.asked_for', null)
        ->assertJsonPath('data.0.entries.0.bodyweight', true)
        ->assertJsonPath('data.0.entries.1.asked_for', 'weight_kg')
        ->assertJsonPath('data.0.entries.1.set_list.0', ['reps' => 10, 'weight_kg' => null, 'duration_seconds' => null, 'distance_meters' => null, 'rpe' => null, 'rir' => null]);
});
