<?php

declare(strict_types=1);

use App\Actions\SmartLog\AnswerOpenQuestion;
use App\Actions\SmartLog\RecordSmartLog;
use App\Ai\Agents\SmartLogAgent;
use App\Ai\SdkSmartLogParser;
use App\Channels\HandleInboundMessage;
use App\Channels\ReplyComposer;
use App\Contracts\Stats\PersonalRecords;
use App\Interpretation\Rules\WeightedLiftNeedsSetsAndReps;
use App\Models\User;
use Laravel\Ai\Ai;
use Tests\Fakes\FakeSmartLogParser;

/*
 * Hiren's real log from 2026-10-22: "Yesterday did squats 5x5 / 90 80 80 80 80 /
 * And ran 1k in 6:30". Stored as five sets at 90 kg before per-set storage.
 */
function squatPyramid(): array
{
    return [
        'log_type' => 'workout',
        'summary' => 'Squats 5x5 (90kg, 80kg x4), Run 1km in 6:30',
        'exercises' => [
            ['exercise_name' => 'Squat', 'sets' => 5, 'reps' => 5, 'weight_kg' => null, 'set_list' => [
                ['reps' => 5, 'weight_kg' => 90.0],
                ['reps' => 5, 'weight_kg' => 80.0],
                ['reps' => 5, 'weight_kg' => 80.0],
                ['reps' => 5, 'weight_kg' => 80.0],
                ['reps' => 5, 'weight_kg' => 80.0],
            ]],
            ['exercise_name' => 'Run', 'distance_meters' => 1000, 'duration_seconds' => 390],
        ],
        'questions' => [],
    ];
}

it('stores each set with its own weight', function (): void {
    $user = User::factory()->create();

    $result = app(RecordSmartLog::class)->handle($user, 'squats 5x5 90 80 80 80 80', squatPyramid());
    $squat = $result->entries[0];

    expect($squat->setRows()->pluck('weight_kg')->map(fn ($w): float => (float) $w)->all())->toBe([90.0, 80.0, 80.0, 80.0, 80.0])
        ->and($squat->setRows()->pluck('reps')->all())->toBe([5, 5, 5, 5, 5])
        ->and($squat->sets)->toBe(5)
        ->and($squat->reps)->toBe(5)
        ->and((float) $squat->weight_kg)->toBe(90.0)
        ->and($squat->volumeKg())->toBe(2050.0)
        ->and($result->session->totalVolumeKg)->toBe(2050.0);
});

it('writes the sets in the receipt', function (): void {
    linkedUser();
    fakeSmartLogParser(new FakeSmartLogParser(squatPyramid()));

    expect(app(HandleInboundMessage::class)->handle(telegramText("Yesterday did squats 5x5\n90 80 80 80 80\n\nAnd ran 1k in 6:30")))
        ->toBe('Logged: Squat 5x5 @ 90, 80, 80, 80, 80 kg, Run 1 km 6:30');
});

it('shows weight and reps per set when the reps differ too', function (): void {
    $user = User::factory()->create();
    $result = app(RecordSmartLog::class)->handle($user, 'bench', workoutFacts([
        ['exercise_name' => 'Bench Press', 'set_list' => [['reps' => 8, 'weight_kg' => 80.0], ['reps' => 6, 'weight_kg' => 85.0], ['reps' => 4, 'weight_kg' => 90.0]]],
    ]));

    expect(app(ReplyComposer::class)->entry($result->entries[0]))->toBe('Bench Press 80×8, 85×6, 90×4 kg')
        ->and($result->entries[0]->reps)->toBeNull()
        ->and($result->entries[0]->volumeKg())->toBe(1510.0);
});

it('turns equal sets into set rows too', function (): void {
    $user = User::factory()->create();
    $result = app(RecordSmartLog::class)->handle($user, 'bench 3x8 80', workoutFacts([lift('Bench Press', 80.0, 3, 8)]));

    expect($result->entries[0]->setRows()->count())->toBe(3)
        ->and(app(ReplyComposer::class)->entry($result->entries[0]))->toBe('Bench Press 3x8 @ 80 kg');
});

it('keeps the top set as the personal record', function (): void {
    $user = User::factory()->create();
    app(RecordSmartLog::class)->handle($user, 'squats', squatPyramid());

    $strength = app(PersonalRecords::class)->for($user)['strength'];

    expect($strength[0]['exercise'])->toBe('Squat')->and($strength[0]['max_weight_kg'])->toBe(90.0);
});

it('fills missing reps on every set from one answer', function (): void {
    $user = User::factory()->create();
    $log = app(RecordSmartLog::class)->handle($user, 'squat 90 80 80', workoutFacts(
        [['exercise_name' => 'Squat', 'set_list' => [['reps' => null, 'weight_kg' => 90.0], ['reps' => null, 'weight_kg' => 80.0], ['reps' => null, 'weight_kg' => 80.0]]]],
        ['questions' => [['field' => 'exercises.0.reps', 'question' => 'How many reps per set for Squat at 90 kg?']]],
    ))->log;

    $entry = app(AnswerOpenQuestion::class)->answer($log, '5');

    expect($entry->setRows()->pluck('reps')->all())->toBe([5, 5, 5])
        ->and($entry->setRows()->pluck('weight_kg')->map(fn ($w): float => (float) $w)->all())->toBe([90.0, 80.0, 80.0])
        ->and($entry->refresh()->reps)->toBe(5);
});

it('rebuilds equal sets when the sets-or-reps question is answered', function (): void {
    $user = User::factory()->create();
    $log = app(RecordSmartLog::class)->handle($user, 'deadlift 90 x3 x5', workoutFacts(
        [['exercise_name' => 'Deadlift', 'sets' => null, 'reps' => null, 'weight_kg' => 90.0]],
        ['questions' => [['field' => 'exercises.0.sets', 'question' => '?', 'pair' => [3, 5]]]],
    ))->log;

    $entry = app(AnswerOpenQuestion::class)->answer($log, '5');

    expect($entry->setRows()->pluck('reps')->all())->toBe([3, 3, 3, 3, 3]);
});

it('reads a set list through the real parser and drops junk sets', function (): void {
    Ai::fakeAgent(SmartLogAgent::class, [[
        'log_type' => 'workout',
        'summary' => 'Squats',
        'exercises' => [['exercise_name' => 'Squat', 'sets' => 2, 'reps' => 5, 'set_list' => [['reps' => '5', 'weight_kg' => '90'], ['reps' => 5, 'weight_kg' => 'heavy']]]],
        'questions' => [],
    ]]);

    $parsed = app(SdkSmartLogParser::class)->parse(User::factory()->make(), 'squats');

    expect($parsed['exercises'][0]['set_list'])->toBe([['reps' => 5, 'weight_kg' => 90.0], ['reps' => 5, 'weight_kg' => null]]);
});

it('does not ask for reps when every listed set has them', function (): void {
    $parsed = (new WeightedLiftNeedsSetsAndReps)->apply('squats', squatPyramid())->parsed;

    expect($parsed['questions'])->toBe([]);
});
