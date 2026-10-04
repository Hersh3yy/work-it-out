<?php

declare(strict_types=1);

namespace App\Actions\SmartLog;

use App\Enums\LogSource;
use App\Jobs\UpdateUserStats;
use App\Models\ActivityLog;
use App\Models\BodyWeightLog;
use App\Models\ExerciseEntry;
use App\Models\NutritionLog;
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
 * values outside sane ranges are clamped or dropped. Stats are recomputed by
 * the queued job only after the transaction commits.
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
                'log_type' => $parsed['log_type'] ?? 'general',
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
                case 'workout':
                    [$session, $merged, $entries] = $this->recordWorkout($user, $log, $parsed, $loggedOn);
                    $log->loggable()->associate($session);
                    break;

                case 'biometrics':
                    $weight = $this->recordWeight($user, $parsed, $loggedOn);
                    $log->loggable()->associate($weight);
                    break;

                case 'meal':
                    $meal = $this->recordMeal($user, $parsed, $loggedOn);
                    $log->loggable()->associate($meal);
                    break;
            }

            $log->save();

            $diary = $log->diaryEntry()->create([
                'user_id' => $user->id,
                'content' => $summary,
            ]);

            return new SmartLogResult($log, $diary, $session, $merged, $entries, $weight);
        });

        if ($result->session !== null) {
            UpdateUserStats::dispatch($user);
        }

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

            $distance = self::clamp($exercise['distance_meters'] ?? null, 1, 300_000);

            $entries[] = $session->exerciseEntries()->create([
                'activity_log_id' => $log->id,
                'exercise_name' => mb_substr($name !== '' ? $name : 'Unknown', 0, 100),
                'sets' => self::clamp($exercise['sets'] ?? null, 1, 50),
                'reps' => self::clamp($exercise['reps'] ?? null, 1, 255),
                'weight_kg' => self::clamp($exercise['weight_kg'] ?? null, 0.5, 500),
                'duration_seconds' => self::clamp($exercise['duration_seconds'] ?? null, 1, 86_400),
                'distance_meters' => $distance === null ? null : (int) round($distance),
                'notes' => $exercise['notes'] ?? null,
                'sort_order' => min($sortOrder++, 255),
            ]);
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
     * Kept until nutrition is removed in M3.
     *
     * @param  array<string, mixed>  $parsed
     */
    private function recordMeal(User $user, array $parsed, CarbonImmutable $loggedOn): ?NutritionLog
    {
        if (empty($parsed['food_name'])) {
            return null;
        }

        return $user->nutritionLogs()->create([
            'logged_at' => $loggedOn->isToday() ? now() : $loggedOn->setTimeFrom(now()),
            'meal_type' => $parsed['meal_type'] ?? 'snack',
            'food_name' => mb_substr((string) $parsed['food_name'], 0, 150),
        ]);
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
