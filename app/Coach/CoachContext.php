<?php

declare(strict_types=1);

namespace App\Coach;

use App\Limitations\ExerciseLoads;
use App\Models\ActivityLog;
use App\Models\CoachTurn;
use App\Models\Limitation;
use App\Models\LimitationUpdate;
use App\Models\User;
use App\Profile\ProfileFacts;
use App\Services\Exercises\ExerciseLine;

/**
 * The living context the coach answers from: what plain code computed
 * (ProfileFacts), the written profile notes, the person's own recent
 * messages with the exercises saved from each (sets and loads, worded as
 * their receipts were), their stated limitations with how each is going and what loads
 * it, and the last few questions they asked with the answers given.
 *
 * Everything here is either a stored fact or the person's own words. The
 * model gets nothing else, so it cannot "remember" what it was never told.
 * Size is capped by config/coach.php, not by how long someone has logged.
 */
final readonly class CoachContext
{
    /**
     * @param  array<string, mixed>  $data
     */
    private function __construct(private array $data) {}

    public static function for(User $user): self
    {
        $facts = ProfileFacts::for($user)->toArray();
        unset($facts['limitations']);

        return new self([
            'today' => today()->toDateString(),
            'facts' => $facts,
            'profile_notes' => array_column($user->profileVersions()->first()?->notes ?? [], 'text'),
            'limitations' => self::limitations($user),
            'recent_logs' => self::recentLogs($user),
            'recent_exchanges' => self::recentTurns($user),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->data;
    }

    public function toJson(): string
    {
        return (string) json_encode($this->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function limitations(User $user): array
    {
        return $user->limitations()->active()->with('updates')->latest('started_on')->get()
            ->map(self::limitation(...))
            ->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    private static function limitation(Limitation $limitation): array
    {
        $howItIsGoing = $limitation->updates
            ->take((int) config('coach.context.limitation_updates'))
            ->map(static fn (LimitationUpdate $update): array => ['date' => $update->logged_on->toDateString(), 'said' => $update->note])
            ->values()->all();

        return [
            'area' => $limitation->areaLabel(),
            'what_they_said' => $limitation->note,
            'severity' => $limitation->severity?->value,
            'since' => $limitation->started_on->toDateString(),
            'day' => $limitation->day(),
            'until' => $limitation->expected_until?->toDateString(),
            'red_flags_screen' => $limitation->red_flags ?? 'not answered',
            'how_it_is_going' => $howItIsGoing,
            'exercises_that_load_it' => $limitation->body_area === null ? [] : ExerciseLoads::exercisesLoading($limitation->body_area),
        ];
    }

    /**
     * Newest first, the person's own words, what was saved, and the saved
     * exercises with their sets and loads.
     *
     * @return list<array<string, mixed>>
     */
    private static function recentLogs(User $user): array
    {
        $lines = app(ExerciseLine::class);

        return $user->activityLogs()->with('exerciseEntries')->limit((int) config('coach.context.recent_logs'))->get()
            ->map(static fn (ActivityLog $log): array => self::log($log, $lines))
            ->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    private static function log(ActivityLog $log, ExerciseLine $lines): array
    {
        $chars = (int) config('coach.context.message_chars');

        return [
            'date' => $log->logged_on->toDateString(),
            'kind' => $log->log_type->value,
            'message' => mb_strimwidth($log->raw_message, 0, $chars, '…'),
            'saved_as' => mb_strimwidth($log->summary, 0, $chars, '…'),
            'exercises' => $log->exerciseEntries->sortBy('sort_order')->map($lines->for(...))->values()->all(),
        ];
    }

    /**
     * The last few questions within the window, oldest first so they read as
     * a conversation.
     *
     * @return list<array<string, string>>
     */
    private static function recentTurns(User $user): array
    {
        $chars = (int) config('coach.context.turn_chars');

        return $user->coachTurns()
            ->where('created_at', '>=', now()->subHours((int) config('coach.context.turn_hours')))
            ->limit((int) config('coach.context.turns'))
            ->get()
            ->reverse()
            ->map(static fn (CoachTurn $t): array => [
                'at' => $t->created_at->format('Y-m-d H:i'),
                'they_asked' => mb_strimwidth($t->question, 0, $chars, '…'),
                'you_answered' => mb_strimwidth($t->answer, 0, $chars, '…'),
            ])->values()->all();
    }
}
