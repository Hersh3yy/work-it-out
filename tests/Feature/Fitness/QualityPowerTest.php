<?php

declare(strict_types=1);

use App\Enums\BodyArea;
use App\Enums\LimitationSeverity;
use App\Fitness\Quality;
use App\Fitness\QualityPower;
use App\Models\User;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-15 12:00'));
});

it('scores strength as the best Epley one-rep max per kilo of body weight against that lift\'s standard', function (): void {
    $user = User::factory()->create(['current_weight_kg' => 80]);
    trainedDaysAgo($user, 3, ['exercise_name' => 'Deadlift', 'set_list' => [['reps' => 5, 'weight_kg' => 120], ['reps' => 5, 'weight_kg' => 100]]]);
    trainedDaysAgo($user, 2, ['exercise_name' => 'Bench Press', 'set_list' => [['reps' => 5, 'weight_kg' => 70]]]);

    $strength = QualityPower::for($user)->of(Quality::Strength);

    expect($strength?->value)->toBe(1.17)
        ->and($strength?->measures)->toMatchArray(['exercise' => 'Deadlift', 'e1rm_kg' => 140.0, 'body_weight_kg' => 80.0, 'e1rm_per_body_weight' => 1.75]);
});

it('counts only sets of up to ten reps toward the one-rep max', function (): void {
    $user = User::factory()->create(['current_weight_kg' => 80]);
    trainedDaysAgo($user, 1, ['exercise_name' => 'Squat', 'set_list' => [['reps' => 12, 'weight_kg' => 140], ['reps' => 10, 'weight_kg' => 60]]]);

    expect(QualityPower::for($user)->of(Quality::Strength)?->measures['e1rm_kg'])->toBe(80.0);
});

it('never counts an assisted machine\'s help as load', function (): void {
    $user = User::factory()->create(['current_weight_kg' => 80]);
    trainedDaysAgo($user, 1, ['exercise_name' => 'Squat', 'assisted' => true, 'set_list' => [['reps' => 5, 'weight_kg' => 40]]]);

    expect(QualityPower::for($user)->of(Quality::Strength))->toBeNull();
});

it('reads an entry without set rows from its summary columns', function (): void {
    $user = User::factory()->create(['current_weight_kg' => 100]);
    trainedDaysAgo($user, 1, ['exercise_name' => 'Squat', 'sets' => 3, 'reps' => 3, 'weight_kg' => 100]);

    expect(QualityPower::for($user)->of(Quality::Strength)?->measures['e1rm_kg'])->toBe(110.0);
});

it('takes body weight from the latest weigh-in on or before the day', function (): void {
    $user = User::factory()->create(['current_weight_kg' => 90]);
    $user->bodyWeightLogs()->create(['logged_at' => today()->subDays(5), 'weight_kg' => 70]);
    trainedDaysAgo($user, 1, ['exercise_name' => 'Bench Press', 'set_list' => [['reps' => 1, 'weight_kg' => 70]]]);

    expect(QualityPower::for($user)->of(Quality::Strength)?->measures['body_weight_kg'])->toBe(70.0);
});

it('leaves strength unscored without a body weight', function (): void {
    $user = User::factory()->create(['current_weight_kg' => null]);
    trainedDaysAgo($user, 1, ['exercise_name' => 'Deadlift', 'set_list' => [['reps' => 5, 'weight_kg' => 120]]]);

    expect(QualityPower::for($user)->of(Quality::Strength))->toBeNull();
});

it('forgets lifts older than eight weeks', function (): void {
    $user = User::factory()->create(['current_weight_kg' => 80]);
    trainedDaysAgo($user, 60, ['exercise_name' => 'Deadlift', 'set_list' => [['reps' => 5, 'weight_kg' => 120]]]);

    expect(QualityPower::for($user)->of(Quality::Strength))->toBeNull();
});

it('scores endurance as cardio minutes in the last seven days against 150', function (): void {
    $user = User::factory()->create();
    trainedDaysAgo($user, 1, ['exercise_name' => 'Run', 'duration_seconds' => 1800, 'distance_meters' => 5000]);
    trainedDaysAgo($user, 3, ['exercise_name' => 'Padel', 'duration_seconds' => 3600]);
    trainedDaysAgo($user, 10, ['exercise_name' => 'Run', 'duration_seconds' => 1800]);

    $endurance = QualityPower::for($user)->of(Quality::Endurance);

    expect($endurance?->value)->toBe(0.6)
        ->and($endurance?->measures['cardio_minutes_7d'])->toBe(90);
});

it('keeps the longest run of the last thirty days and forgets older runs', function (): void {
    $user = User::factory()->create();
    trainedDaysAgo($user, 10, ['exercise_name' => 'Run', 'distance_meters' => 5000, 'duration_seconds' => 1800]);
    trainedDaysAgo($user, 40, ['exercise_name' => 'Run', 'distance_meters' => 8000, 'duration_seconds' => 3000]);

    $endurance = QualityPower::for($user)->of(Quality::Endurance);

    expect($endurance?->measures['longest_run_30d_m'])->toBe(5000)
        ->and($endurance?->value)->toBe(0.0);
});

it('scores core from the longest timed hold and the heaviest carry, whichever is further along', function (): void {
    $user = User::factory()->create(['current_weight_kg' => 80]);
    trainedDaysAgo($user, 2, ['exercise_name' => 'Plank', 'duration_seconds' => 90]);
    trainedDaysAgo($user, 4, ['exercise_name' => "Farmer's carry", 'weight_kg' => 32, 'distance_meters' => 40]);

    $core = QualityPower::for($user)->of(Quality::Core);

    expect($core?->value)->toBe(0.8)
        ->and($core?->measures)->toMatchArray(['longest_hold_seconds' => 90, 'heaviest_carry_kg' => 32.0]);
});

it('scores recovery from rest days in the last seven against two', function (): void {
    $user = User::factory()->create();
    foreach ([0, 1, 2, 3, 4, 5] as $daysAgo) {
        trainedDaysAgo($user, $daysAgo, ['exercise_name' => 'Squat', 'sets' => 3, 'reps' => 5, 'weight_kg' => 60]);
    }

    $recovery = QualityPower::for($user)->of(Quality::Recovery);

    expect($recovery?->value)->toBe(0.5)
        ->and($recovery?->measures['rest_days_7d'])->toBe(1);
});

it('lowers recovery for each active limitation by its severity', function (): void {
    $user = User::factory()->create();
    trainedDaysAgo($user, 1, ['exercise_name' => 'Squat', 'sets' => 3, 'reps' => 5, 'weight_kg' => 60]);
    $user->limitations()->create(['body_area' => BodyArea::LowerBack, 'severity' => LimitationSeverity::Moderate, 'note' => 'tweaked it', 'started_on' => today()->subDays(3)]);
    $user->limitations()->create(['body_area' => BodyArea::Knee, 'severity' => LimitationSeverity::Severe, 'note' => 'old one', 'started_on' => today()->subDays(30), 'resolved_at' => now()->subDays(10)]);

    $recovery = QualityPower::for($user)->of(Quality::Recovery);

    expect($recovery?->value)->toBe(0.75)
        ->and($recovery?->measures['active_limitations'])->toBe(1);
});

it('leaves mobility and power unscored until a test exists, never zero', function (): void {
    $user = User::factory()->create(['current_weight_kg' => 80]);
    trainedDaysAgo($user, 1, ['exercise_name' => 'Box jump', 'sets' => 3, 'reps' => 5], ['exercise_name' => 'Stretching', 'duration_seconds' => 600]);

    $power = QualityPower::for($user);

    expect($power->of(Quality::Mobility))->toBeNull()
        ->and($power->of(Quality::Power))->toBeNull();
});

it('leaves every quality unscored for someone who never trained', function (): void {
    $user = User::factory()->create(['current_weight_kg' => 80]);

    expect(QualityPower::for($user)->toArray())->toBe(array_fill_keys(array_column(Quality::cases(), 'value'), null));
});
