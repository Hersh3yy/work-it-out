<?php

declare(strict_types=1);

use App\Jobs\UpdateUserStats;
use App\Models\User;
use App\Models\WorkoutSession;

it('computes weekly adherence rate correctly', function (): void {
    $user = User::factory()->create(['training_days_per_week' => 4]);

    // 2 completed sessions this week
    WorkoutSession::factory()->for($user)->count(2)->completed()->create([
        'logged_at' => now()->startOfWeek()->addDay(),
    ]);

    (new UpdateUserStats($user))->handle();

    $user->refresh();

    // 2 / 4 = 50%
    expect((float) $user->weekly_adherence_rate)->toBe(50.0);
});

it('computes current streak correctly', function (): void {
    $user = User::factory()->create();

    // Sessions on the last 3 consecutive days
    foreach (range(0, 2) as $daysAgo) {
        WorkoutSession::factory()->for($user)->create([
            'logged_at' => now()->subDays($daysAgo)->setTime(8, 0),
        ]);
    }

    (new UpdateUserStats($user))->handle();

    $user->refresh();

    expect($user->current_streak_days)->toBe(3);
});

it('returns zero streak when no sessions exist', function (): void {
    $user = User::factory()->create();

    (new UpdateUserStats($user))->handle();

    $user->refresh();

    expect($user->current_streak_days)->toBe(0);
});

it('caps adherence rate at 100', function (): void {
    $user = User::factory()->create(['training_days_per_week' => 3]);

    // 5 completed sessions — more than planned
    WorkoutSession::factory()->for($user)->count(5)->completed()->create([
        'logged_at' => now()->startOfWeek()->addDay(),
    ]);

    (new UpdateUserStats($user))->handle();

    $user->refresh();

    expect((float) $user->weekly_adherence_rate)->toBeLessThanOrEqual(100.0);
});
