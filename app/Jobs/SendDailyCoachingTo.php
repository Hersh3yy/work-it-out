<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Coach\SendDailyCoaching;
use App\Exceptions\AiUnavailable;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * One person's morning message, in the background so a slow model call
 * never holds up the others. An AI outage comes back in ten minutes, three
 * tries; after the last the person gets one line saying so, never a day
 * skipped in silence and never a full message at noon. A try after today's
 * message was made does nothing, so a retry never asks the coach again or
 * repeats the message.
 */
final class SendDailyCoachingTo implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 600;

    public int $uniqueFor = 3600;

    public function __construct(public readonly User $user) {}

    public function uniqueId(): string
    {
        return $this->user->id.':'.today()->toDateString();
    }

    public function handle(SendDailyCoaching $send): void
    {
        if ($this->user->coachTurns()->dailySince(today())->exists()) {
            return;
        }

        try {
            $send->handle($this->user);
        } catch (AiUnavailable) {
            $this->attempts() < $this->tries ? $this->release(600) : $send->tellCouldNotWrite($this->user);
        }
    }
}
