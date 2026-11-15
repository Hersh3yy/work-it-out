<?php

declare(strict_types=1);

use App\Actions\SmartLog\RecordSmartLog;
use App\Actions\SmartLog\RevertSmartLog;
use App\Events\LogRecorded;
use App\Events\LogReverted;
use App\Fitness\Quality;
use App\Fitness\QualityLevel;
use App\Listeners\RefreshQualitySnapshots;
use App\Models\QualitySnapshot;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-11 18:00'));
});

it('listens to every saved and undone log', function (): void {
    Event::fake();

    Event::assertListening(LogRecorded::class, [RefreshQualitySnapshots::class, 'handleRecorded']);
    Event::assertListening(LogReverted::class, [RefreshQualitySnapshots::class, 'handleReverted']);
});

it('writes one row per quality per day, however many logs that day has', function (): void {
    $user = User::factory()->create(['current_weight_kg' => 80]);

    app(RecordSmartLog::class)->handle($user, 'deadlift 5x5 100', workoutFacts([lift('Deadlift', 100.0, 5, 5)]));
    $this->travel(4)->hours();
    app(RecordSmartLog::class)->handle($user, 'ran 5k in 30 min', workoutFacts([['exercise_name' => 'Run', 'distance_meters' => 5000, 'duration_seconds' => 1800]]));

    $rows = QualitySnapshot::query()->where('user_id', $user->id)->get();

    expect($rows)->toHaveCount(6)
        ->and($rows->firstWhere('quality', Quality::Strength)?->level_points)->toBe(1)
        ->and($rows->firstWhere('quality', Quality::Endurance)?->level_points)->toBe(1)
        ->and($rows->firstWhere('quality', Quality::Core)?->level_points)->toBe(0)
        ->and($rows->firstWhere('quality', Quality::Strength)?->power)->toBe(0.97)
        ->and($rows->firstWhere('quality', Quality::Strength)?->readiness)->toBe(40)
        ->and($rows->firstWhere('quality', Quality::Mobility)?->power)->toBeNull();
});

it('rewrites the day after an undo', function (): void {
    $user = User::factory()->create(['current_weight_kg' => 80]);
    $result = app(RecordSmartLog::class)->handle($user, 'deadlift 5x5 100', workoutFacts([lift('Deadlift', 100.0, 5, 5)]));

    app(RevertSmartLog::class)->handle($result->log);

    $strength = QualitySnapshot::query()->where('user_id', $user->id)->where('quality', Quality::Strength)->get();

    expect($strength)->toHaveCount(1)
        ->and($strength->first()?->level_points)->toBe(0)
        ->and($strength->first()?->readiness)->toBe(100)
        ->and(QualityLevel::for($user)->of(Quality::Strength))->toBe(0);
});

it('never lowers a level through rest or injury: level is the sum of points earned', function (): void {
    $user = User::factory()->create(['current_weight_kg' => 80]);
    $levels = [];

    app(RecordSmartLog::class)->handle($user, 'deadlift 5x5 100', workoutFacts([lift('Deadlift', 100.0, 5, 5)]));
    $levels[] = QualityLevel::for($user)->of(Quality::Strength);

    $this->travel(3)->days();
    $user->limitations()->create(['body_area' => 'lower_back', 'severity' => 'severe', 'note' => 'pulled it', 'started_on' => today()]);
    app(RecordSmartLog::class)->handle($user, '81kg', ['log_type' => 'biometrics', 'summary' => 'Weight 81 kg', 'weight_kg_stat' => 81.0, 'exercises' => [], 'questions' => []]);
    $levels[] = QualityLevel::for($user)->of(Quality::Strength);

    $this->travel(2)->days();
    app(RecordSmartLog::class)->handle($user, 'squat 3x5 80', workoutFacts([lift('Squat', 80.0, 3, 5)]));
    $levels[] = QualityLevel::for($user)->of(Quality::Strength);

    expect($levels)->toBe([1, 1, 2]);
});
