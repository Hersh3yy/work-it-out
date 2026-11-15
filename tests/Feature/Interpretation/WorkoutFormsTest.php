<?php

declare(strict_types=1);

use App\Actions\SmartLog\RecordSmartLog;
use App\Channels\ReplyComposer;
use App\Enums\MessageKind;
use App\Interpretation\Interpreter;
use Tests\Fakes\FakeSmartLogParser;

/*
 * The forms in tests/Evals/workouts.txt, each as the reader should return it
 * (the fake parser), through the named rules and the writer. The number the
 * log ends up with is the app's arithmetic, never the model's.
 */
function readForm(string $text, array $exercises, array $overrides = []): array
{
    $user = linkedUser();
    classifierSays(MessageKind::Workout);
    fakeSmartLogParser(new FakeSmartLogParser(workoutFacts($exercises, $overrides)));

    $interpretation = app(Interpreter::class)->interpret($user, $text);
    $result = $interpretation->shouldRecord() ? app(RecordSmartLog::class)->handle($user, $text, $interpretation->parsed) : null;

    return [
        'parsed' => $interpretation->parsed,
        'reply' => $interpretation->reply,
        'lines' => $result === null ? [] : array_map(app(ReplyComposer::class)->entry(...), $result->entries),
        'question' => $interpretation->parsed['questions'][0]['question'] ?? null,
    ];
}

it('takes "10 pushups" as complete: bodyweight, nothing to ask', function (): void {
    $read = readForm('10 pushups', [
        ['exercise_name' => 'Push-ups', 'sets' => 1, 'reps' => 10, 'bodyweight' => true],
    ], ['questions' => [['field' => 'exercises.0.weight_kg', 'question' => 'What weight for the push-ups?']]]);

    expect($read['question'])->toBeNull()
        ->and($read['lines'])->toBe(['Push-ups 10 reps (bodyweight)']);
});

it('reads the assisted machine\'s kg as help from the name alone', function (): void {
    $read = readForm('Did 5x5 assisted pull up (45kg)', [
        ['exercise_name' => 'Assisted Pull-up', 'sets' => 5, 'reps' => 5, 'weight_kg' => 45.0, 'bodyweight' => true],
    ]);

    expect($read['parsed']['exercises'][0]['assisted'])->toBeTrue()
        ->and($read['parsed']['exercises'][0]['bodyweight'])->toBeFalse()
        ->and($read['lines'])->toBe(['Assisted Pull-up 5x5, 45 kg help']);
});

it('gives every exercise of a superset the rounds', function (): void {
    $read = readForm("Did a superset\n10 pushups\n12 lying leg raises\n40 second plank\n\n4 of those", [
        ['exercise_name' => 'Push-ups', 'sets' => 4, 'reps' => 10, 'bodyweight' => true, 'group' => 'Superset'],
        ['exercise_name' => 'Lying Leg Raise', 'sets' => 1, 'reps' => 12, 'bodyweight' => true, 'group' => 'Superset'],
        ['exercise_name' => 'Plank', 'sets' => null, 'duration_seconds' => 40, 'bodyweight' => true, 'group' => 'Superset'],
    ]);

    expect(array_column($read['parsed']['exercises'], 'sets'))->toBe([4, 4, 4])
        ->and($read['lines'])->toBe(['Push-ups 4x10 (bodyweight)', 'Lying Leg Raise 4x12 (bodyweight)', 'Plank 4x40s']);
});

it('converts pounds and miles itself and keeps what was said', function (): void {
    $read = readForm('bench 3x8 185 lb, ran 3 miles in 27 min', [
        ['exercise_name' => 'Bench Press', 'sets' => 3, 'reps' => 8, 'weight_lb' => 185.0],
        ['exercise_name' => 'Run', 'distance_miles' => 3.0, 'duration_seconds' => 1620],
    ]);

    expect($read['parsed']['exercises'][0]['weight_kg'])->toBe(83.91)
        ->and($read['parsed']['exercises'][0]['notes'])->toBe('185 lb')
        ->and($read['parsed']['exercises'][1]['distance_meters'])->toBe(4828)
        ->and($read['lines'])->toBe(['Bench Press 3x8 @ 83.91 kg', 'Run 4.83 km 27 min']);
});

it('doubles plates per side and says the bar is not counted', function (): void {
    $read = readForm('preacher curls, curl bar, 11.25kg a side, 3x10', [
        ['exercise_name' => 'Preacher Curl', 'sets' => 3, 'reps' => 10, 'per_side_kg' => 11.25],
    ]);

    expect($read['parsed']['exercises'][0]['weight_kg'])->toBe(22.5)
        ->and($read['parsed']['exercises'][0]['notes'])->toBe('11.25 kg a side, bar not counted')
        ->and($read['question'])->toBeNull();
});

it('works the time out from a pace and a distance', function (): void {
    $read = readForm('ran 5k at 6:00/km', [
        ['exercise_name' => 'Run', 'distance_meters' => 5000, 'pace_seconds_per_km' => 360],
    ]);

    expect($read['parsed']['exercises'][0]['duration_seconds'])->toBe(1800)
        ->and($read['lines'])->toBe(['Run 5 km 30 min']);
});

it('still asks the reps of "3 more sets of bench press 70"', function (): void {
    $read = readForm('I did 3 more sets of bench press 70', [
        ['exercise_name' => 'Bench Press', 'sets' => 3, 'reps' => null, 'weight_kg' => 70.0],
    ]);

    expect($read['question'])->toBe('How many reps per set for Bench Press at 70 kg?');
});

it('keeps intervals as sets with a distance each and no weight question', function (): void {
    $read = readForm('8x400m', [
        ['exercise_name' => 'Run', 'sets' => 8, 'distance_meters' => 400],
    ]);

    expect($read['question'])->toBeNull()
        ->and($read['lines'])->toBe(['Run 8x400 m']);
});

it('keeps the effort said for all sets and for one set', function (): void {
    $read = readForm('squat 5x5 100 left 2 in the tank; bench 80 3 sets last to failure at 6', [
        ['exercise_name' => 'Squat', 'sets' => 5, 'reps' => 5, 'weight_kg' => 100.0, 'rir' => 2],
        ['exercise_name' => 'Bench Press', 'set_list' => [
            ['reps' => 8, 'weight_kg' => 80.0], ['reps' => 8, 'weight_kg' => 80.0], ['reps' => 6, 'weight_kg' => 80.0, 'rir' => 0],
        ]],
    ]);

    expect($read['lines'])->toBe(['Squat 5x5 @ 100 kg, 2 in reserve', 'Bench Press 80 kg × 8, 8, 6 (to failure)']);
});
