<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Fitness\Quality;
use App\Fitness\QualityLevel;
use App\Fitness\QualityPower;
use App\Fitness\QualityReadiness;
use App\Fitness\TrainingHistory;
use App\Models\QualitySnapshot;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Rewrites one person's six quality rows for one day from what is stored
 * now, so a save and an undo both leave the day true. Safe to run twice.
 */
final class WriteQualitySnapshots implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly User $user,
        private readonly CarbonImmutable $day,
    ) {}

    public function handle(): void
    {
        $history = TrainingHistory::for($this->user, $this->day);
        $power = QualityPower::from($history);
        $readiness = QualityReadiness::from($history);

        QualitySnapshot::query()->upsert(
            array_map(fn (Quality $quality): array => [
                'user_id' => $this->user->id,
                'quality' => $quality->value,
                'day' => $this->day->toDateString(),
                'level_points' => QualityLevel::pointsOn($history, $quality),
                'power' => $power->of($quality)?->value,
                'readiness' => $readiness->of($quality)->value,
                'created_at' => now(),
                'updated_at' => now(),
            ], Quality::cases()),
            uniqueBy: ['user_id', 'quality', 'day'],
            update: ['level_points', 'power', 'readiness', 'updated_at'],
        );
    }
}
