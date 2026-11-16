<?php

declare(strict_types=1);

namespace App\Goals;

/** The kind of goal a message names, and the word it is called by. */
final readonly class GoalWord
{
    public function __construct(
        public GoalKind $kind,
        public string $name,
    ) {}
}
