<?php

declare(strict_types=1);

use App\Actions\SmartLog\RecordSmartLog;
use App\Contracts\Stats\PersonalRecords;
use App\Models\DiaryEntry;
use App\Models\User;

it('counts one log as one training day for adherence', function (): void {
    $user = User::factory()->create(['training_days_per_week' => 4]);

    app(RecordSmartLog::class)->handle($user, 'bench 3x8 80', workoutFacts([lift('Bench Press')]));

    expect((float) $user->refresh()->weekly_adherence_rate)->toBe(25.0);
    $this->assertDatabaseHas('workout_sessions', ['user_id' => $user->id, 'completed_planned' => true]);
});

it('writes nothing when any step fails', function (): void {
    $user = User::factory()->create();
    DiaryEntry::creating(fn () => throw new RuntimeException('disk full'));

    expect(fn () => app(RecordSmartLog::class)->handle($user, 'bench 3x8 80', workoutFacts([lift('Bench Press')])))
        ->toThrow(RuntimeException::class);

    $this->assertDatabaseCount('activity_logs', 0);
    $this->assertDatabaseCount('workout_sessions', 0);
    $this->assertDatabaseCount('exercise_entries', 0);
});

it('joins two messages from one gym visit into one session', function (): void {
    $this->travelTo(today()->setTime(18, 0));
    $user = User::factory()->create();
    $record = app(RecordSmartLog::class);

    $first = $record->handle($user, 'bench 3x8 80', workoutFacts([lift('Bench Press')]));
    $this->travel(40)->minutes();
    $second = $record->handle($user, 'incline 3x10 30', workoutFacts([lift('Incline DB Press', 30.0, 3, 10)]));

    expect($second->addedToExisting)->toBeTrue()
        ->and($second->session->id)->toBe($first->session->id)
        ->and($user->workoutSessions()->count())->toBe(1)
        ->and($first->session->exerciseEntries()->pluck('sort_order')->all())->toBe([0, 1])
        ->and($second->entries)->toHaveCount(1);
});

it('starts a new session after the merge window', function (): void {
    $this->travelTo(today()->setTime(8, 0));
    $user = User::factory()->create();
    $record = app(RecordSmartLog::class);

    $record->handle($user, 'bench', workoutFacts([lift('Bench Press')]));
    $this->travel(4)->hours();
    $later = $record->handle($user, 'run', workoutFacts([['exercise_name' => 'Run', 'distance_meters' => 5000]]));

    expect($later->addedToExisting)->toBeFalse()
        ->and($user->workoutSessions()->count())->toBe(2);
});

it('keeps three spellings of one lift as one personal record', function (): void {
    $user = User::factory()->create();
    $record = app(RecordSmartLog::class);

    foreach ([['bench', 80.0], ['Bench Press', 90.0], ['benchpress', 85.0]] as $day => [$name, $kg]) {
        $this->travelTo(today()->subDays(3 - $day)->setTime(18, 0));
        $record->handle($user, "{$name} {$kg}", workoutFacts([lift($name, $kg)]));
    }

    $strength = app(PersonalRecords::class)->for($user)['strength'];

    expect($strength)->toHaveCount(1)
        ->and($strength[0]['exercise'])->toBe('Bench Press')
        ->and($strength[0]['max_weight_kg'])->toBe(90.0);
});

it('reuses the user\'s own spelling of an exercise it has no alias for', function (): void {
    $user = User::factory()->create();
    $record = app(RecordSmartLog::class);

    $this->travelTo(today()->subDay()->setTime(18, 0));
    $record->handle($user, 'x', workoutFacts([lift('Incline DB Press', 30.0)]));
    $this->travelBack();
    $result = $record->handle($user, 'x', workoutFacts([lift('incline db press', 32.5)]));

    expect($result->entries[0]->exercise_name)->toBe('Incline DB Press');
});

it('dates a "yesterday" log yesterday and never merges it into today', function (): void {
    $user = User::factory()->create();
    $yesterday = today()->subDay()->toDateString();

    $result = app(RecordSmartLog::class)->handle(
        $user,
        'yesterday ran 5k in 28 min',
        workoutFacts([['exercise_name' => 'Run', 'distance_meters' => 5000, 'duration_seconds' => 1680]], ['logged_on' => $yesterday]),
    );

    expect($result->session->logged_at->toDateString())->toBe($yesterday)
        ->and($result->log->logged_on->toDateString())->toBe($yesterday);
});

it('falls back to today for a date in the future or over a week back', function (string $date): void {
    $user = User::factory()->create();

    $result = app(RecordSmartLog::class)->handle($user, 'x', workoutFacts([lift('Squat')], ['logged_on' => $date]));

    expect($result->log->logged_on->toDateString())->toBe(today()->toDateString());
})->with([
    'tomorrow' => fn () => today()->addDay()->toDateString(),
    'eight days back' => fn () => today()->subDays(8)->toDateString(),
    'not a date' => 'last tuesday',
]);

it('drops values below range and clamps values above it', function (): void {
    $user = User::factory()->create();

    $result = app(RecordSmartLog::class)->handle($user, 'x', workoutFacts(
        [['exercise_name' => 'Squat', 'sets' => 0, 'reps' => 900, 'weight_kg' => -5, 'distance_meters' => 1234.6]],
        ['perceived_exertion' => 15, 'duration_minutes' => 0],
    ));

    $entry = $result->entries[0];

    expect($entry->sets)->toBeNull()
        ->and($entry->reps)->toBe(255)
        ->and($entry->weight_kg)->toBeNull()
        ->and($entry->distance_meters)->toBe(1235)
        ->and($result->session->perceived_exertion)->toBe(10)
        ->and($result->session->duration_minutes)->toBeNull();
});

it('stores open questions on the log', function (): void {
    $user = User::factory()->create();
    $question = ['field' => 'exercises.0.weight_kg', 'question' => 'What weight for the incline press?'];

    $result = app(RecordSmartLog::class)->handle($user, 'incline 3x10', workoutFacts([lift('Incline DB Press', null)], ['questions' => [$question]]));

    expect($result->log->fresh()->questions)->toBe([$question])
        ->and($result->entries[0]->weight_kg)->toBeNull();
});

it('records a stated body weight and updates the current weight', function (): void {
    $user = User::factory()->create(['current_weight_kg' => 105]);

    $result = app(RecordSmartLog::class)->handle($user, '104.5kg', [
        'log_type' => 'biometrics', 'summary' => 'Weight 104.5 kg', 'weight_kg_stat' => 104.5,
    ]);

    expect((float) $result->weight->weight_kg)->toBe(104.5)
        ->and((float) $user->refresh()->current_weight_kg)->toBe(104.5)
        ->and($result->log->loggable_id)->toBe($result->weight->id);
});

it('writes a diary line and no row for a general message', function (): void {
    $user = User::factory()->create();

    $result = app(RecordSmartLog::class)->handle($user, 'walked the dog', ['log_type' => 'general', 'summary' => 'Walked the dog']);

    expect($result->diary->content)->toBe('Walked the dog')
        ->and($result->log->loggable_id)->toBeNull();
    $this->assertDatabaseCount('workout_sessions', 0);
});
