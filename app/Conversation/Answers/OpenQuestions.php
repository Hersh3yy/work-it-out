<?php

declare(strict_types=1);

namespace App\Conversation\Answers;

use App\Models\ActivityLog;
use App\Models\User;
use Carbon\CarbonInterface;

/**
 * Finds the question the person is most likely answering: the one on their
 * newest log that still has one, for six hours. A newer log without a
 * question does not hide an older one.
 */
final readonly class OpenQuestions
{
    private const int OPEN_HOURS = 6;

    public function for(User $user): ?OpenQuestion
    {
        $log = $user->activityLogs()
            ->where('created_at', '>=', now()->subHours(self::OPEN_HOURS))
            ->whereNotNull('questions')
            ->get()
            ->first(static fn (ActivityLog $log): bool => ! empty($log->questions));
        $open = $log === null ? null : OpenQuestion::on($log);

        return $open === null || $this->closedByTheCoach($user, $open) ? null : $open;
    }

    private function closedByTheCoach(User $user, OpenQuestion $open): bool
    {
        return $open->kind->closesWhenTheCoachSpeaks() && $this->coachSpokeSince($user, $open->log->updated_at);
    }

    private function coachSpokeSince(User $user, CarbonInterface $askedAt): bool
    {
        return $user->coachTurns()->where('created_at', '>', $askedAt)->exists();
    }
}
