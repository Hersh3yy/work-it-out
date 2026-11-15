<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Exceptions\AiUnavailable;
use App\Models\ProfileVersion;
use App\Models\User;
use App\Profile\ProfileChanges;
use App\Profile\UpdateProfile;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Writes a new version of one user's written profile in the background,
 * once per session: when it runs while the person logged again within the
 * session gap, it queues a fresh job for the new session end (a fresh job, so
 * the tries stay for AI outages) and stops; a profile already written after
 * the last change means there is nothing to do. A full rebuild never waits.
 *
 * Unique per user while it waits, so five messages in a row make one update.
 * The lock lifts when it starts, so a log sent during the AI call queues the next one.
 * An AI outage (a 503, a timeout) is already reported by AiCall; the job
 * comes back after profile.rolling.retry_seconds until its tries run out.
 */
final class BuildProfile implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly User $user,
        public readonly string $kind = ProfileVersion::ROLLING,
    ) {}

    public function uniqueId(): string
    {
        return $this->user->id.':'.$this->kind;
    }

    /** Held while it waits for the session end, plus room to start. */
    public function uniqueFor(): int
    {
        return ((int) config('profile.rolling.session_gap_minutes') + 10) * 60;
    }

    public function handle(UpdateProfile $update): void
    {
        $changedAt = $this->kind === ProfileVersion::ROLLING ? ProfileChanges::lastAt($this->user) : null;

        if ($changedAt !== null && $this->writtenAfter($changedAt)) {
            return;
        }

        if ($changedAt !== null && $this->sessionStillGoing($changedAt)) {
            self::dispatch($this->user)->delay(ProfileChanges::sessionEndAfter($changedAt));

            return;
        }

        $this->write($update);
    }

    private function write(UpdateProfile $update): void
    {
        try {
            $update->handle($this->user, $this->kind);
        } catch (AiUnavailable) {
            if ($this->attempts() < $this->tries) {
                $this->release((int) config('profile.rolling.retry_seconds'));
            }
        }
    }

    private function writtenAfter(CarbonImmutable $changedAt): bool
    {
        $latest = $this->user->profileVersions()->first();

        return $latest !== null && $latest->created_at->greaterThan($changedAt->startOfSecond());
    }

    /** The sync queue ignores delays, so there a job writes at once instead of queueing itself forever. */
    private function sessionStillGoing(CarbonImmutable $changedAt): bool
    {
        return ProfileChanges::sessionEndAfter($changedAt)->isFuture() && $this->job?->getConnectionName() !== 'sync';
    }
}
