<?php

declare(strict_types=1);

namespace App\Fitness;

/**
 * One quality's current measure: how far along its reference standard the
 * person is (1.0 is at the standard) and the raw numbers it came from.
 */
final readonly class PowerReading
{
    /**
     * @param  array<string, float|int|string|null>  $measures
     */
    private function __construct(
        public float $value,
        public array $measures,
    ) {}

    /**
     * @param  array<string, float|int|string|null>  $measures
     */
    public static function against(float $measured, float $standard, array $measures): self
    {
        return new self(round($measured / $standard, 2), $measures);
    }

    /**
     * @return array{value: float, measures: array<string, float|int|string|null>}
     */
    public function toArray(): array
    {
        return ['value' => $this->value, 'measures' => $this->measures];
    }
}
