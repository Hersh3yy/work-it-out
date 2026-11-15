<?php

declare(strict_types=1);

namespace App\Fitness;

use Carbon\CarbonImmutable;

/**
 * How ready one quality is to train today, 0 to 100, with when it last
 * worked and what is holding it down.
 */
final readonly class ReadinessReading
{
    /**
     * @param  list<string>  $limitedBy  area labels of active limitations, or "illness"
     */
    public function __construct(
        public int $value,
        public ?CarbonImmutable $lastTrainedOn,
        public array $limitedBy,
    ) {}

    /**
     * @return array{value: int, last_trained_on: string|null, limited_by: list<string>}
     */
    public function toArray(): array
    {
        return ['value' => $this->value, 'last_trained_on' => $this->lastTrainedOn?->toDateString(), 'limited_by' => $this->limitedBy];
    }
}
