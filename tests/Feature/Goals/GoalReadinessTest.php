<?php

declare(strict_types=1);

use App\Fitness\Quality;
use App\Fitness\QualityPower;
use App\Goals\GoalKind;
use App\Goals\GoalPhase;
use App\Goals\GoalReadiness;
use App\Goals\Preparedness;
use App\Models\Goal;
use App\Models\User;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-15 12:00'));
});

function raveFor(User $user): Goal
{
    return $user->goals()->create(['kind' => GoalKind::Event, 'name' => 'rave', 'description' => 'rave', 'target_on' => '2026-12-13']);
}

it('builds further out than 14 days and tapers from 14 days in', function (): void {
    $user = User::factory()->create();
    $goal = raveFor($user);
    $power = QualityPower::for($user);

    expect(GoalReadiness::for($goal, $power, 15)->phase)->toBe(GoalPhase::Build)
        ->and(GoalReadiness::for($goal, $power, 14)->phase)->toBe(GoalPhase::Taper);
});

it('calls a demanded quality ready at its standard, close from three quarters, not yet below', function (int $minutes, Preparedness $expected): void {
    $user = User::factory()->create();
    trainedDaysAgo($user, 1, ['exercise_name' => 'Run', 'duration_seconds' => $minutes * 60]);

    $readiness = GoalReadiness::for(raveFor($user), QualityPower::for($user), 28);

    expect($readiness->of(Quality::Endurance))->toBe($expected);
})->with([
    [150, Preparedness::Ready],
    [120, Preparedness::Close],
    [30, Preparedness::NotYet],
]);

it('never calls an unscored quality ready', function (): void {
    $user = User::factory()->create();
    trainedDaysAgo($user, 1, ['exercise_name' => 'Yoga', 'duration_seconds' => 3600]);

    expect(GoalReadiness::for(raveFor($user), QualityPower::for($user), 28)->of(Quality::Mobility))->toBe(Preparedness::NotMeasured);
});
