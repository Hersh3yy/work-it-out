<?php

declare(strict_types=1);

namespace App\Fitness;

use App\Models\User;
use Carbon\CarbonInterface;

/**
 * Each quality's level: the sum of the points its sessions earned, day by
 * day. Points are never negative, so rest and injury never take a level away.
 */
final readonly class QualityLevel
{
    /**
     * @param  array<string, int>  $levels  quality value => level
     */
    private function __construct(private array $levels) {}

    public static function for(User $user, ?CarbonInterface $on = null): self
    {
        $sums = $user->qualitySnapshots()
            ->whereDate('day', '<=', ($on ?? today())->toDateString())
            ->groupBy('quality')
            ->selectRaw('quality, sum(level_points) as points')
            ->pluck('points', 'quality');

        $levels = [];

        foreach (Quality::cases() as $quality) {
            $levels[$quality->value] = (int) ($sums[$quality->value] ?? 0);
        }

        return new self($levels);
    }

    public function of(Quality $quality): int
    {
        return $this->levels[$quality->value];
    }

    /**
     * @return array<string, int>
     */
    public function toArray(): array
    {
        return $this->levels;
    }

    /**
     * The points one day's sessions earned a quality.
     */
    public static function pointsOn(TrainingHistory $history, Quality $quality): int
    {
        $sessions = $history->training($quality, 1)->pluck('workout_session_id')->unique()->count();

        return $sessions * (int) config('fitness.level.points_per_session');
    }
}
