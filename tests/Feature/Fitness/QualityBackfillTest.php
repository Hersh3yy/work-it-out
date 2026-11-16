<?php

declare(strict_types=1);

use App\Fitness\Quality;
use App\Fitness\QualityLevel;
use App\Models\User;
use Carbon\CarbonImmutable;

it('earns levels for the history logged before snapshots existed', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-15 12:00'));
    $user = User::factory()->create(['email' => 'h@example.com']);
    trainedDaysAgo($user, 30, ['exercise_name' => 'Squat', 'sets' => 3, 'reps' => 5, 'weight_kg' => 80]);
    trainedDaysAgo($user, 12, ['exercise_name' => 'Bench Press', 'sets' => 3, 'reps' => 5, 'weight_kg' => 60]);
    trainedDaysAgo($user, 2, ['exercise_name' => 'Run', 'duration_seconds' => 1800]);

    $this->artisan('quality:backfill', ['email' => 'h@example.com'])->assertSuccessful();
    $this->artisan('quality:backfill', ['email' => 'h@example.com'])->assertSuccessful();

    expect(QualityLevel::for($user)->of(Quality::Strength))->toBe(2)
        ->and(QualityLevel::for($user)->of(Quality::Endurance))->toBe(1)
        ->and($user->qualitySnapshots()->count())->toBe(4 * 6);
});

it('asks for an email or --all', function (): void {
    $this->artisan('quality:backfill')->assertFailed();
});
