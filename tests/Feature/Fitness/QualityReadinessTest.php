<?php

declare(strict_types=1);

use App\Enums\BodyArea;
use App\Enums\LimitationSeverity;
use App\Fitness\Quality;
use App\Fitness\QualityReadiness;
use App\Models\User;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-15 12:00'));
});

it('lowers strength the day after a deadlift and has it full again two days later, endurance untouched', function (): void {
    $user = User::factory()->create();
    trainedDaysAgo($user, 1, ['exercise_name' => 'Deadlift', 'sets' => 5, 'reps' => 5, 'weight_kg' => 100]);

    $today = QualityReadiness::for($user);
    $inTwoDays = QualityReadiness::for($user, today()->addDays(2));

    expect($today->of(Quality::Strength)->value)->toBe(40)
        ->and($today->of(Quality::Endurance)->value)->toBe(100)
        ->and($inTwoDays->of(Quality::Strength)->value)->toBe(100);
});

it('brings a quality back to 70 on the second day after it worked', function (): void {
    $user = User::factory()->create();
    trainedDaysAgo($user, 2, ['exercise_name' => 'Run', 'duration_seconds' => 1800]);

    expect(QualityReadiness::for($user)->of(Quality::Endurance)->value)->toBe(70);
});

it('reports the day each quality was last trained', function (): void {
    $user = User::factory()->create();
    trainedDaysAgo($user, 5, ['exercise_name' => 'Squat', 'sets' => 3, 'reps' => 5, 'weight_kg' => 80]);
    trainedDaysAgo($user, 2, ['exercise_name' => 'Bench Press', 'sets' => 3, 'reps' => 5, 'weight_kg' => 60]);

    $readiness = QualityReadiness::for($user);

    expect($readiness->of(Quality::Strength)->lastTrainedOn?->toDateString())->toBe('2026-11-13')
        ->and($readiness->of(Quality::Endurance)->lastTrainedOn)->toBeNull();
});

it('never tires mobility or recovery with their own light work', function (): void {
    $user = User::factory()->create();
    trainedDaysAgo($user, 1, ['exercise_name' => 'Yoga', 'duration_seconds' => 1800]);

    $readiness = QualityReadiness::for($user);

    expect($readiness->of(Quality::Mobility)->value)->toBe(100)
        ->and($readiness->of(Quality::Recovery)->value)->toBe(100)
        ->and($readiness->of(Quality::Mobility)->lastTrainedOn?->toDateString())->toBe('2026-11-14');
});

it('caps the qualities that load an active limitation\'s area by its severity', function (): void {
    $user = User::factory()->create();
    $user->limitations()->create(['body_area' => BodyArea::LowerBack, 'severity' => LimitationSeverity::Moderate, 'note' => 'tweaked lifting', 'started_on' => today()->subDays(2)]);

    $readiness = QualityReadiness::for($user);

    expect($readiness->of(Quality::Strength)->value)->toBe(60)
        ->and($readiness->of(Quality::Core)->value)->toBe(60)
        ->and($readiness->of(Quality::Strength)->limitedBy)->toBe(['lower back'])
        ->and($readiness->of(Quality::Endurance)->value)->toBe(100);
});

it('caps every quality during an illness', function (): void {
    $user = User::factory()->create();
    $user->limitations()->create(['body_area' => BodyArea::Other, 'severity' => LimitationSeverity::Mild, 'note' => 'flu, staying in bed', 'started_on' => today()->subDay()]);

    $readiness = QualityReadiness::for($user);

    foreach (Quality::cases() as $quality) {
        expect($readiness->of($quality)->value)->toBe(50)
            ->and($readiness->of($quality)->limitedBy)->toBe(['illness']);
    }
});

it('takes the lower of the training dip and a limitation cap', function (): void {
    $user = User::factory()->create();
    trainedDaysAgo($user, 2, ['exercise_name' => 'Deadlift', 'sets' => 5, 'reps' => 5, 'weight_kg' => 100]);
    $user->limitations()->create(['body_area' => BodyArea::LowerBack, 'severity' => LimitationSeverity::Severe, 'note' => 'pulled it', 'started_on' => today()]);

    expect(QualityReadiness::for($user)->of(Quality::Strength)->value)->toBe(30);
});
