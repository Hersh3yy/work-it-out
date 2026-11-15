<?php

declare(strict_types=1);

namespace App\Fitness;

use App\Models\User;
use Carbon\CarbonInterface;

/**
 * Each quality's power, the current measure over a recent window: it goes
 * up with training and down without. Plain code over the person's own
 * entries; the coach and the app quote it, never compute it.
 */
final readonly class QualityPower
{
    /**
     * @param  array<string, PowerReading|null>  $readings  quality value => reading
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
            $readings[$quality->value] = $quality->powerMeasure()->measure($history);
        }

        return new self($readings);
    }

    public function of(Quality $quality): ?PowerReading
    {
        return $this->readings[$quality->value];
    }

    /**
     * @return array<string, array{value: float, measures: array<string, float|int|string|null>}|null>
     */
    public function toArray(): array
    {
        return array_map(static fn (?PowerReading $reading): ?array => $reading?->toArray(), $this->readings);
    }
}
