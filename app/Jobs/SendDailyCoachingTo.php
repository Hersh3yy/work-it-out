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
 * tries; after that the day is skipped rather than nagging at noon.
 */
final class SendDailyCoachingTo implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $uniqueFor = 3600;

    public function __construct(public readonly User $user) {}

    public function uniqueId(): string
    {
        return $this->user->id.':'.today()->toDateString();
    }

    public function handle(SendDailyCoaching $send): void
    {
        try {
            $send->handle($this->user);
        } catch (AiUnavailable) {
            if ($this->attempts() < $this->tries) {
                $this->release(600);
            }
        }
    }
}
