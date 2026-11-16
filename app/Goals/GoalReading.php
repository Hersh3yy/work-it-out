<?php

declare(strict_types=1);

namespace App\Goals;

use Carbon\CarbonImmutable;

/** A goal read from a message, before it is saved. */
final readonly class GoalReading
{
    public function __construct(
        public GoalKind $kind,
        public string $name,
        public string $description,
        public ?CarbonImmutable $targetOn,
    ) {}

    /**
     * @param  array{kind: string, name: string, description: string, target_on: string|null}  $goal
     */
    public static function fromArray(array $goal): self
    {
        return new self(
            GoalKind::from($goal['kind']),
            $goal['name'],
            $goal['description'],
            $goal['target_on'] === null ? null : CarbonImmutable::parse($goal['target_on']),
        );
    }

    /**
     * @return array{kind: string, name: string, description: string, target_on: string|null}
     */
    public function toArray(): array
    {
        return ['kind' => $this->kind->value, 'name' => $this->name, 'description' => $this->description, 'target_on' => $this->targetOn?->toDateString()];
    }
}
