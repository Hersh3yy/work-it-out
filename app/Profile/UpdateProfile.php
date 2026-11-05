<?php

declare(strict_types=1);

namespace App\Profile;

use App\Ai\Agents\ProfileAgent;
use App\Contracts\Ai\ProfileBuilder;
use App\Exceptions\AiUnavailable;
use App\Models\ActivityLog;
use App\Models\CoachTurn;
use App\Models\ProfileVersion;
use App\Models\User;
use App\Services\Ai\AiCall;
use App\Services\Exercises\ExerciseLine;
use Illuminate\Support\Collection;

/**
 * Writes a new version of the qualitative profile.
 *
 * Rolling (after a log): the current notes plus the last few logs and coach
 * questions, so the work stays the same size however long the history
 * grows. Full (weekly, or on request): no previous notes and more logs,
 * which resets any drift.
 *
 * Every note must cite an entry the writer was shown, an entry a kept note
 * already cited that still exists, or "facts". Anything else is dropped, so
 * an undone log's notes disappear at the next update.
 */
final readonly class UpdateProfile
{
    public const string FACTS = 'facts';

    /** A coach question's id as the writer sees it: "coach:12". */
    public const string COACH = 'coach:';

    public function __construct(
        private ProfileBuilder $builder,
        private AiCall $ai,
        private ExerciseLine $lines,
    ) {}

    /**
     * The new version, or null when there was nothing to write about.
     *
     * @throws AiUnavailable
     */
    public function handle(User $user, string $kind = ProfileVersion::ROLLING): ?ProfileVersion
    {
        $full = $kind === ProfileVersion::FULL;
        $logs = $user->activityLogs()->with('exerciseEntries')->limit((int) config($full ? 'profile.rolling.full_logs' : 'profile.rolling.recent_logs'))->get();
        $turns = $user->coachTurns()->limit((int) config('profile.rolling.recent_turns'))->get();
        $previous = $full ? [] : ($user->profileVersions()->first()?->notes ?? []);

        if ($logs->isEmpty() && $turns->isEmpty() && $previous === []) {
            return null;
        }

        $entries = $this->entries($logs, $turns);
        $facts = ProfileFacts::for($user);
        $notes = $this->ai->run($user, ProfileAgent::class, fn (): array => $this->builder->build($facts, $previous, $entries));
        $notes = $this->checked($user, $notes, array_column($entries, 'id'), $previous);

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
     * Logs and coach questions as one list, newest first.
     *
     * @param  Collection<int, ActivityLog>  $logs
     * @param  Collection<int, CoachTurn>  $turns
     * @return list<array{id: string, date: string, type: string, message: string, saved_as: string}>
     */
    private function entries(Collection $logs, Collection $turns): array
    {
        return $logs->map($this->fromLog(...))
            ->concat($turns->map($this->fromTurn(...)))
            ->sortByDesc('at')
            ->map(static fn (array $entry): array => array_diff_key($entry, ['at' => null]))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function fromLog(ActivityLog $log): array
    {
        $exercises = $log->exerciseEntries->sortBy('sort_order')->map($this->lines->for(...))->implode('; ');

        return [
            'at' => $log->created_at,
            'id' => $log->id,
            'date' => $log->logged_on->toDateString(),
            'type' => $log->log_type->value,
            'message' => $log->raw_message,
            'saved_as' => $exercises === '' ? $log->summary : $log->summary.' ('.$exercises.')',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fromTurn(CoachTurn $turn): array
    {
        return [
            'at' => $turn->created_at,
            'id' => self::COACH.$turn->id,
            'date' => $turn->created_at->toDateString(),
            'type' => 'coach_question',
            'message' => $turn->question,
            'saved_as' => 'coach answered: '.mb_strimwidth($turn->answer, 0, 300, '…'),
        ];
    }

    /**
     * Keeps citations to entries that exist for this user (or "facts");
     * drops notes left with none.
     *
     * @param  list<array{text: string, cites: list<string>}>  $notes
     * @param  list<string>  $shown
     * @param  list<array{text: string, cites: list<string>}>  $previous
     * @return list<array{text: string, cites: list<string>}>
     */
    private function checked(User $user, array $notes, array $shown, array $previous): array
    {
        $earlier = array_values(array_diff(array_merge([], ...array_column($previous, 'cites')), [self::FACTS]));
        $allowed = array_flip(array_merge($shown, $this->stillThere($user, $earlier), [self::FACTS]));

        $kept = [];

        foreach ($notes as $note) {
            $cites = array_values(array_unique(array_filter($note['cites'], static fn (string $c): bool => isset($allowed[$c]))));

            if ($cites !== []) {
                $kept[] = ['text' => $note['text'], 'cites' => $cites];
            }
        }

        return array_slice($kept, 0, (int) config('profile.rolling.max_notes'));
    }

    /**
     * Which of these earlier cites still point at something of this user's.
     *
     * @param  list<string>  $cites
     * @return list<string>
     */
    private function stillThere(User $user, array $cites): array
    {
        if ($cites === []) {
            return [];
        }

        $coach = array_filter($cites, static fn (string $c): bool => str_starts_with($c, self::COACH));
        $logs = array_diff($cites, $coach);
        $turnIds = array_map(static fn (string $c): int => (int) substr($c, strlen(self::COACH)), $coach);

        return [
            ...($logs === [] ? [] : $user->activityLogs()->whereIn('id', $logs)->pluck('id')->all()),
            ...($turnIds === [] ? [] : $user->coachTurns()->whereIn('id', $turnIds)->pluck('id')->map(static fn (int $id): string => self::COACH.$id)->all()),
        ];
    }
}
