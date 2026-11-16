<?php

declare(strict_types=1);

namespace App\Actions\SmartLog;

use App\Enums\LogSource;
use App\Enums\LogType;
use App\Events\LogRecorded;
use App\Models\ActivityLog;
use App\Models\BodyWeightLog;
use App\Models\ExerciseEntry;
use App\Models\User;
use App\Models\WorkoutSession;
use App\Services\Exercises\ExerciseAliases;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Persists the facts of one parsed free-text log, all or nothing.
 *
 * The one write path both doors use (HTTP now, Telegram in M5). Domain rules
 * live here: a message within three hours of today's session joins it,
 * exercise names are made canonical, a stated day may be up to a week back,
 * values outside sane ranges are clamped or dropped. LogRecorded fires only
 * after the transaction commits; stats and later the profile listen to it.
 */
final readonly class RecordSmartLog
{
    private const int MERGE_WINDOW_HOURS = 3;

    private const int MAX_DAYS_BACK = 7;

    /**
     * @param  array<string, mixed>  $parsed  normalized SmartLogParser payload
     */
    public function handle(User $user, string $message, array $parsed, LogSource $source = LogSource::Http): SmartLogResult
    {
        $loggedOn = $this->loggedOn($parsed['logged_on'] ?? null);

        $result = DB::transaction(function () use ($user, $message, $parsed, $source, $loggedOn): SmartLogResult {
            $summary = trim((string) ($parsed['summary'] ?? '')) ?: mb_substr($message, 0, 255);

            $log = $user->activityLogs()->create([
                'source' => $source,
                'log_type' => LogType::tryFrom((string) ($parsed['log_type'] ?? '')) ?? LogType::General,
                'raw_message' => $message,
                'summary' => $summary,
                'questions' => ($parsed['questions'] ?? []) ?: null,
                'logged_on' => $loggedOn->toDateString(),
            ]);

            $session = null;
            $merged = false;
            $entries = [];
            $weight = null;

            switch ($log->log_type) {
                case LogType::Workout:
                    [$session, $merged, $entries] = $this->recordWorkout($user, $log, $parsed, $loggedOn);
                    $log->loggable()->associate($session);
                    break;

                case LogType::Biometrics:
                    $weight = $this->recordWeight($user, $parsed, $loggedOn);
                    $log->loggable()->associate($weight);
                    break;

                case LogType::General:
                    break;
            }

            $log->save();

            $diary = $log->diaryEntry()->create([
                'user_id' => $user->id,
                'content' => $summary,
            ]);

            return new SmartLogResult($log, $diary, $session, $merged, $entries, $weight);
        });

        LogRecorded::dispatch($result->log);

        return $result;
    }

    /**
     * A stated day within the last week, else today. Never the future.
     */
    private function loggedOn(mixed $date): CarbonImmutable
    {
        $today = CarbonImmutable::today();

        if (! is_string($date) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $today;
        }

        $day = CarbonImmutable::createFromFormat('!Y-m-d', $date);

        if ($day === false || $day->isAfter($today) || $day->isBefore($today->subDays(self::MAX_DAYS_BACK))) {
            return $today;
        }

        return $day;
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return array{0: WorkoutSession, 1: bool, 2: list<ExerciseEntry>}
     */
    private function recordWorkout(User $user, ActivityLog $log, array $parsed, CarbonImmutable $loggedOn): array
    {
        $session = $loggedOn->isToday() ? $this->openSessionToday($user) : null;
        $merged = $session !== null;

        $session ??= $user->workoutSessions()->create([
            'logged_at' => $loggedOn->isToday() ? now() : $loggedOn->setTimeFrom(now()),
            'duration_minutes' => self::clamp($parsed['duration_minutes'] ?? null, 1, 600),
            'perceived_exertion' => self::clamp($parsed['perceived_exertion'] ?? null, 1, 10),
            'energy_level' => self::clamp($parsed['energy_level'] ?? null, 1, 5),
            'notes' => $parsed['workout_notes'] ?? null,
            'completed_planned' => true,
        ]);

        $knownNames = ExerciseEntry::query()
            ->whereIn('workout_session_id', $user->workoutSessions()->withTrashed()->select('id'))
            ->distinct()
            ->pluck('exercise_name')
            ->all();

        $sortOrder = $merged ? (int) $session->exerciseEntries()->max('sort_order') + 1 : 0;
        $entries = [];

        foreach ($parsed['exercises'] ?? [] as $exercise) {
            $name = ExerciseAliases::canonical((string) ($exercise['exercise_name'] ?? ''), $knownNames);
            $knownNames[] = $name;

            $values = self::setValues($exercise);

            $entry = $session->exerciseEntries()->create([
                'activity_log_id' => $log->id,
                'exercise_name' => mb_substr($name !== '' ? $name : 'Unknown', 0, 100),
                'sets' => self::clamp($exercise['sets'] ?? null, 1, 50),
                'reps' => $values['reps'],
                'weight_kg' => $values['weight_kg'],
                'duration_seconds' => $values['duration_seconds'],
                'distance_meters' => $values['distance_meters'],
                'notes' => $exercise['notes'] ?? null,
                'bodyweight' => (bool) ($exercise['bodyweight'] ?? false),
                'assisted' => (bool) ($exercise['assisted'] ?? false),
                'group_label' => self::groupLabel($exercise['group'] ?? null),
                'sort_order' => min($sortOrder++, 255),
            ]);

            $sets = self::setsFor($exercise, $values);

            if ($sets !== []) {
                $entry->replaceSets($sets);
            }

            $entries[] = $entry->refresh();
        }

        return [$session, $merged, $entries];
    }

    /**
     * The latest session of today, if it started within the merge window.
     */
    private function openSessionToday(User $user): ?WorkoutSession
    {
        return $user->workoutSessions()
            ->where('logged_at', '>=', now()->subHours(self::MERGE_WINDOW_HOURS))
            ->where('logged_at', '>=', today())
            ->latest('logged_at')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $parsed
     */
    private function recordWeight(User $user, array $parsed, CarbonImmutable $loggedOn): ?BodyWeightLog
    {
        $weightKg = self::clamp($parsed['weight_kg_stat'] ?? null, 20, 400);

        if ($weightKg === null) {
            return null;
        }

        $log = $user->bodyWeightLogs()->create([
            'logged_at' => $loggedOn->toDateString(),
            'weight_kg' => $weightKg,
        ]);

        if ($loggedOn->isToday()) {
            $user->update(['current_weight_kg' => $weightKg]);
        }

        return $log;
    }

    /**
     * The sets of one exercise: the listed sets when they differ, else the
     * exercise's own values repeated for the number of sets. A listed set
     * missing a value takes the exercise's. With sets counted, the exercise's
     * time and distance are per set ("3 x 45 s plank", "8x400m"); without,
     * they are the whole ("ran 5k in 28") and need no rows.
     *
     * @param  array<string, mixed>  $exercise
     * @param  array<string, int|float|null>  $values  the exercise's own, one key per ExerciseSet::VALUES
     * @return list<array<string, int|float|null>>
     */
    private static function setsFor(array $exercise, array $values): array
    {
        $listed = is_array($exercise['set_list'] ?? null) ? $exercise['set_list'] : [];

        if ($listed !== []) {
            return array_map(
                static fn (array $set): array => array_replace($values, array_filter(self::setValues($set), static fn ($own): bool => $own !== null)),
                array_slice($listed, 0, 50),
            );
        }

        $count = self::clamp($exercise['sets'] ?? null, 1, 50);

        return $count === null ? [] : array_fill(0, (int) $count, $values);
    }

    /**
     * @param  array<string, mixed>  $set
     * @return array<string, int|float|null>
     */
    private static function setValues(array $set): array
    {
        $distance = self::clamp($set['distance_meters'] ?? null, 1, 300_000);

        return [
            'reps' => self::clamp($set['reps'] ?? null, 1, 255),
            'weight_kg' => self::clamp($set['weight_kg'] ?? null, 0.5, 500),
            'duration_seconds' => self::clamp($set['duration_seconds'] ?? null, 1, 86_400),
            'distance_meters' => $distance === null ? null : (int) round($distance),
            'rpe' => self::clamp($set['rpe'] ?? null, 1, 10),
            'rir' => self::clamp($set['rir'] ?? null, 0, 20),
        ];
    }

    private static function groupLabel(mixed $group): ?string
    {
        return is_string($group) && trim($group) !== '' ? mb_substr(trim($group), 0, 40) : null;
    }

    /**
     * Below the minimum is no value at all; above the maximum is the maximum.
     */
    private static function clamp(mixed $value, int|float $min, int|float $max): int|float|null
    {
        if (! is_numeric($value) || $value < $min) {
            return null;
        }

        return min($value + 0, $max);
    }
}
