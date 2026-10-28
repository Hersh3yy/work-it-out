<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\LogRecorded;
use App\Events\LogReverted;
use App\Jobs\BuildProfile;
use App\Models\User;

/**
 * Updates the written profile shortly after any log is saved or undone.
 */
final class RefreshProfile
{
    public function handleRecorded(LogRecorded $event): void
    {
        $this->queue($event->log->user);
    }

    public function handleReverted(LogReverted $event): void
    {
        $this->queue($event->user);
    }

    private function queue(User $user): void
    {
        if (config('profile.rolling.enabled')) {
            BuildProfile::dispatch($user)->delay(now()->addSeconds((int) config('profile.rolling.delay_seconds')));
        }
    }
}
