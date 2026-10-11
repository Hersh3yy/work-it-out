<?php

declare(strict_types=1);

use App\Interpretation\Rules\RepsTimesSetsHasTwoReadings;
use App\Interpretation\Rules\WeightedLiftNeedsSetsAndReps;

/*
 * Hiren's real session from 2026-10-10, as the parser read it.
 */
const SESSION_TEXT = "today i did deadlifts :\n2x70kh\n2x110kg x 5 times\n\noverhead press\n2x 5x45kg\n3x 5x40kg but with a clean to start\nran for 20 min about 2 miles";

function sessionParsed(): array
{
    return [
        'log_type' => 'workout',
        'exercises' => [
            ['exercise_name' => 'Deadlift', 'sets' => 2, 'reps' => null, 'weight_kg' => 70.0],
            ['exercise_name' => 'Deadlift', 'sets' => 2, 'reps' => 5, 'weight_kg' => 110.0],
            ['exercise_name' => 'Overhead Press', 'sets' => 2, 'reps' => 5, 'weight_kg' => 45.0],
            ['exercise_name' => 'Overhead Press', 'sets' => 3, 'reps' => 5, 'weight_kg' => 40.0],
            ['exercise_name' => 'Run', 'distance_meters' => 3218.68, 'duration_seconds' => 1200],
        ],
        'questions' => [],
    ];
}

it('asks about "2x110kg x 5 times" even when another line matches first', function (): void {
    $parsed = (new RepsTimesSetsHasTwoReadings)->apply(SESSION_TEXT, sessionParsed())->parsed;

    expect($parsed['questions'][0]['field'])->toBe('exercises.1.sets')
        ->and($parsed['questions'][0]['pair'])->toBe([2, 5])
        ->and($parsed['exercises'][1]['sets'])->toBeNull()
        ->and($parsed['exercises'][2]['sets'])->toBe(2);
});

it('asks for the missing reps of a lift with a weight', function (): void {
    $parsed = (new WeightedLiftNeedsSetsAndReps)->apply('deadlift 2x70kg', [
        'exercises' => [['exercise_name' => 'Deadlift', 'sets' => 2, 'reps' => null, 'weight_kg' => 70.0]],
        'questions' => [],
    ])->parsed;

    expect($parsed['questions'][0])->toBe([
        'field' => 'exercises.0.reps',
        'question' => 'How many reps per set for Deadlift at 70 kg?',
    ]);
});

it('leaves bodyweight and cardio entries alone', function (): void {
    $input = [
        'exercises' => [
            ['exercise_name' => 'Pull-up', 'sets' => 3, 'reps' => null, 'weight_kg' => null],
            ['exercise_name' => 'Run', 'distance_meters' => 5000],
        ],
        'questions' => [],
    ];

    expect((new WeightedLiftNeedsSetsAndReps)->apply('pull-ups 3 sets, ran 5k', $input)->parsed)->toBe($input);
});

it('does not add a second question while one is open', function (): void {
    $input = sessionParsed();
    $input['questions'] = [['field' => 'exercises.1.sets', 'question' => 'Was that 2 sets of 5 reps, or 5 sets of 2 reps?']];

    expect((new WeightedLiftNeedsSetsAndReps)->apply(SESSION_TEXT, $input)->parsed['questions'])->toHaveCount(1);
});
