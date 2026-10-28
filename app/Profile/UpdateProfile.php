<?php

declare(strict_types=1);

namespace App\Profile;

use App\Ai\Agents\ProfileAgent;
use App\Contracts\Ai\ProfileBuilder;
use App\Exceptions\AiUnavailable;
use App\Models\ActivityLog;
use App\Models\ProfileVersion;
use App\Models\User;
use App\Services\Ai\AiCall;

/**
 * Writes a new version of the qualitative profile.
 *
 * Rolling (after a log): the current notes plus the last few logs, so the
 * work stays the same size however long the history grows. Full (weekly, or
 * on request): no previous notes and more logs, which resets any drift.
 *
 * Every note must cite a log the writer was shown, a log a kept note already
 * cited that still exists, or "facts". Anything else is dropped, so an undone
 * log's notes disappear at the next update.
 */
final readonly class UpdateProfile
{
    public const string FACTS = 'facts';

    public function __construct(
        private ProfileBuilder $builder,
        private AiCall $ai,
    ) {}

    /**
     * The new version, or null when there was nothing to write about.
     *
     * @throws AiUnavailable
     */
    public function handle(User $user, string $kind = ProfileVersion::ROLLING): ?ProfileVersion
    {
        $full = $kind === ProfileVersion::FULL;
        $logs = $user->activityLogs()->limit((int) config($full ? 'profile.rolling.full_logs' : 'profile.rolling.recent_logs'))->get();
        $previous = $full ? [] : ($user->profileVersions()->first()?->notes ?? []);

        if ($logs->isEmpty() && $previous === []) {
            return null;
        }

        $recentLogs = $logs->map(static fn (ActivityLog $log): array => [
            'id' => $log->id,
            'date' => $log->logged_on->toDateString(),
            'type' => $log->log_type->value,
            'message' => $log->raw_message,
            'saved_as' => $log->summary,
        ])->values()->all();

        $facts = ProfileFacts::for($user);
        $notes = $this->ai->run($user, ProfileAgent::class, fn (): array => $this->builder->build($facts, $previous, $recentLogs));
        $notes = $this->checked($user, $notes, $logs->pluck('id')->all(), $previous);

        if ($notes === []) {
            return null;
        }

        return $user->profileVersions()->create([
            'kind' => $kind,
            'notes' => $notes,
            'based_on_log_id' => $logs->first()?->id,
            'model' => $this->builder->modelName(),
        ]);
    }

    /**
     * Keeps citations to logs that exist for this user (or "facts"); drops
     * notes left with none.
     *
     * @param  list<array{text: string, cites: list<string>}>  $notes
     * @param  list<string>  $shown
     * @param  list<array{text: string, cites: list<string>}>  $previous
     * @return list<array{text: string, cites: list<string>}>
     */
    private function checked(User $user, array $notes, array $shown, array $previous): array
    {
        $earlier = array_values(array_diff(array_merge([], ...array_column($previous, 'cites')), [self::FACTS]));
        $stillThere = $earlier === [] ? [] : $user->activityLogs()->whereIn('id', $earlier)->pluck('id')->all();
        $allowed = array_flip(array_merge($shown, $stillThere, [self::FACTS]));

        $kept = [];

        foreach ($notes as $note) {
            $cites = array_values(array_unique(array_filter($note['cites'], static fn (string $c): bool => isset($allowed[$c]))));

            if ($cites !== []) {
                $kept[] = ['text' => $note['text'], 'cites' => $cites];
            }
        }

        return array_slice($kept, 0, (int) config('profile.rolling.max_notes'));
    }
}
