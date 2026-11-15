<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\LogRecorded;
use App\Events\LogReverted;
use App\Jobs\WriteQualitySnapshots;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Rewrites the quality rows for the log's day and for today whenever a log
 * is saved or undone: a weigh-in moves strength, a limitation readiness.
 */
final class RefreshQualitySnapshots
{
    public function handleRecorded(LogRecorded $event): void
    {
        $this->refresh($event->log->user, $event->log->logged_on->toImmutable());
    }

    public function handleReverted(LogReverted $event): void
    {
        $this->refresh($event->user, $event->loggedOn);
    }

    private function refresh(User $user, CarbonImmutable $day): void
    {
        WriteQualitySnapshots::dispatch($user, $day->startOfDay());

        if (! $day->isToday()) {
            WriteQualitySnapshots::dispatch($user, CarbonImmutable::today());
        }
    }
}
