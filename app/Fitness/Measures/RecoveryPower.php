<?php

declare(strict_types=1);

namespace App\Fitness\Measures;

use App\Enums\LimitationSeverity;
use App\Fitness\PowerReading;
use App\Fitness\TrainingHistory;
use App\Models\Limitation;

/**
 * Rest days this week against the standard, never above it (more idle days
 * are not more recovered), lowered by each active limitation's severity.
 */
final readonly class RecoveryPower implements PowerMeasure
{
    public function measure(TrainingHistory $history): ?PowerReading
    {
        if (! $history->hasEverTrained) {
            return null;
        }

        $restDays = $history->restDaysIn((int) config('fitness.power.rest_window_days'));
        $restShare = min(1.0, $restDays / (int) config('fitness.power.rest_days_standard'));
        $left = $history->activeLimitations->reduce(fn (float $share, Limitation $limitation): float => $share * (1 - $this->penalty($limitation)), 1.0);

        return PowerReading::against($restShare * $left, 1.0, [
            'rest_days_7d' => $restDays,
            'active_limitations' => $history->activeLimitations->count(),
        ]);
    }

    private function penalty(Limitation $limitation): float
    {
        $severity = $limitation->severity ?? LimitationSeverity::Moderate;

        return (float) config("fitness.power.limitation_penalty.{$severity->value}");
    }
}
