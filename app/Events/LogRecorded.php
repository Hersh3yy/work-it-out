<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\ActivityLog;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A free-text log was saved. Dispatched after the transaction commits, so
 * listeners (stats now; profile notes and the check-in planner later) only
 * ever see data that is really there.
 *
 * @see https://refactoring.guru/design-patterns/observer
 */
final readonly class LogRecorded
{
    use Dispatchable;

    public function __construct(
        public ActivityLog $log,
    ) {}
}
