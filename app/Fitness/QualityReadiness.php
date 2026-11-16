<?php

declare(strict_types=1);

namespace App\Fitness;

use App\Enums\LimitationSeverity;
use App\Models\Limitation;
use App\Models\User;
use Carbon\CarbonInterface;

/**
 * Today's readiness per quality: lower the day it worked and the day after,
 * full again from the third day, capped while a limitation sits on an area
 * it loads or an illness is on file. It never blocks; it lightens the day.
 */
final readonly class QualityReadiness
{
    /**
     * @param  array<string, ReadinessReading>  $readings  quality value => reading
     */
    private function __construct(private array $readings) {}

    public static function for(User $user, ?CarbonInterface $on = null): self
    {
        return self::from(TrainingHistory::for($user, $on));
    }

    public static function from(TrainingHistory $history): self
    {
        $readings = [];

        foreach (Quality::cases() as $quality) {
            $readings[$quality->value] = self::reading($history, $quality);
        }

        return new self($readings);
    }

    public function of(Quality $quality): ReadinessReading
    {
        return $this->readings[$quality->value];
    }

    /**
     * @return array<string, array{value: int, last_trained_on: string|null, limited_by: list<string>}>
     */
    public function toArray(): array
    {
        return array_map(static fn (ReadinessReading $reading): array => $reading->toArray(), $this->readings);
    }

    private static function reading(TrainingHistory $history, Quality $quality): ReadinessReading
    {
        $lastTrained = $history->lastTrained($quality);
        $value = $quality->tiresWhenTrained() ? self::afterTraining($history, $lastTrained) : (int) config('fitness.readiness.full');
        $limitedBy = [];

        foreach ($history->activeLimitations as $limitation) {
            $cap = self::capFor($limitation, $quality);

            if ($cap !== null) {
                $value = min($value, $cap);
                $limitedBy[] = $limitation->isIllness() ? 'illness' : $limitation->areaLabel();
            }
        }

        return new ReadinessReading($value, $lastTrained, $limitedBy);
    }

    private static function afterTraining(TrainingHistory $history, ?CarbonInterface $lastTrained): int
    {
        $daysSince = $lastTrained === null ? null : (int) $lastTrained->diffInDays($history->on);

        return (int) (config("fitness.readiness.after_training.{$daysSince}") ?? config('fitness.readiness.full'));
    }

    private static function capFor(Limitation $limitation, Quality $quality): ?int
    {
        if ($limitation->isIllness()) {
            return (int) config('fitness.readiness.illness_cap');
        }

        if ($limitation->body_area === null || ! in_array($limitation->body_area, $quality->leansOn(), true)) {
            return null;
        }

        $severity = $limitation->severity ?? LimitationSeverity::Moderate;

        return (int) config("fitness.readiness.limitation_cap.{$severity->value}");
    }
}
