<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\LogRecorded;
use App\Events\LogReverted;
use App\Jobs\BuildProfile;
use App\Models\User;
use App\Profile\ProfileChanges;

/**
 * Marks a change whenever a log is saved or undone, and queues the profile
 * update for the end of the session (ProfileChanges).
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
        if (! config('profile.rolling.enabled')) {
            return;
        }

        ProfileChanges::markNow($user);
        BuildProfile::dispatch($user)->delay(ProfileChanges::sessionEndAfter(now()->toImmutable()));
    }
}
