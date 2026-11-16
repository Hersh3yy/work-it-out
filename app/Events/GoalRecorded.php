<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Goal;
use Illuminate\Foundation\Events\Dispatchable;

/** A goal was saved or got its date. Dispatched after the transaction commits. */
final readonly class GoalRecorded
{
    use Dispatchable;

    public function __construct(
        public Goal $goal,
    ) {}
}
