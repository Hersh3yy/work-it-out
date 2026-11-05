<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Exceptions\AiUnavailable;
use App\Models\ProfileVersion;
use App\Models\User;
use App\Profile\UpdateProfile;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Writes a new version of one user's written profile in the background.
 *
 * Unique per user while it waits, so five messages in a row make one update.
 * The lock lifts when it starts, so a log sent during the AI call queues the next one.
 * An AI outage (a 503, a timeout) is already reported by AiCall; the job
 * comes back after profile.rolling.retry_seconds until its tries run out.
 */
final class BuildProfile implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 600;

    public int $tries = 3;

    public function __construct(
        public readonly User $user,
        public readonly string $kind = ProfileVersion::ROLLING,
    ) {}

    public function uniqueId(): string
    {
        return $this->user->id.':'.$this->kind;
    }

    public function handle(UpdateProfile $update): void
    {
        try {
            $update->handle($this->user, $this->kind);
        } catch (AiUnavailable) {
            if ($this->attempts() < $this->tries) {
                $this->release((int) config('profile.rolling.retry_seconds'));
            }
        }
    }
}
