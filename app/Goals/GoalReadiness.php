<?php

declare(strict_types=1);

namespace App\Goals;

use App\Fitness\Quality;
use App\Fitness\QualityPower;
use App\Models\Goal;

/**
 * How ready a person is for a dated goal: per quality it demands, how far
 * its power is toward the standard, and whether the time left says build or
 * taper. Reads power, never today's readiness.
 */
final readonly class GoalReadiness
{
    /**
     * @param  array<string, Preparedness>  $qualities  quality value => preparedness, in the goal's order
     */
    private function __construct(
        public GoalPhase $phase,
        public int $daysLeft,
        private array $qualities,
    ) {}

    public static function for(Goal $goal, QualityPower $power, int $daysLeft): self
    {
        $qualities = [];

        foreach ($goal->kind->demands() as $quality) {
            $qualities[$quality->value] = Preparedness::of($power->of($quality));
        }

        return new self(GoalPhase::for($daysLeft), $daysLeft, $qualities);
    }

    public function of(Quality $quality): ?Preparedness
    {
        return $this->qualities[$quality->value] ?? null;
    }

    /** "Endurance close, recovery ready, mobility not measured yet" */
    public function describe(): string
    {
        $parts = [];

        foreach ($this->qualities as $quality => $preparedness) {
            $parts[] = $quality.' '.$preparedness->label();
        }

        return ucfirst(implode(', ', $parts));
    }

    /**
     * @return array{phase: string, days_left: int, qualities: array<string, string>}
     */
    public function toArray(): array
    {
        return [
            'phase' => $this->phase->value,
            'days_left' => $this->daysLeft,
            'qualities' => array_map(static fn (Preparedness $p): string => $p->value, $this->qualities),
        ];
    }
}
