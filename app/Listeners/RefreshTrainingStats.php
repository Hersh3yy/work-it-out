<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\LogRecorded;
use App\Events\LogReverted;
use App\Jobs\UpdateUserStats;

/**
 * Recomputes adherence, streak and last activity when training data changes.
 */
final class RefreshTrainingStats
{
    public function handleRecorded(LogRecorded $event): void
    {
        if ($event->log->log_type->touchesTraining()) {
            UpdateUserStats::dispatch($event->log->user);
        }
    }

    public function handleReverted(LogReverted $event): void
    {
        if ($event->type->touchesTraining()) {
            UpdateUserStats::dispatch($event->user);
        }
    }
}
