<?php

declare(strict_types=1);

namespace App\Actions\SmartLog;

use App\Interpretation\NumberReply;
use App\Models\ActivityLog;
use App\Models\ExerciseEntry;
use App\Models\User;

/**
 * Fills in the value a log's open question asked for, without AI.
 *
 * The question names its field ("exercises.1.weight_kg", or one set's value,
 * "exercises.0.set_list.5.weight_kg"), so a reply like "30", "32.5 kg", "5k"
 * or "28 min" can be written straight into that entry or set. A unit that
 * does not fit the field ("12 kg" for reps) is not an answer. A sets-or-reps
 * question carries the two counts, so the answer fills both. Only the user's
 * latest log, and only for six hours.
 */
final readonly class AnswerOpenQuestion
{
    private const int OPEN_HOURS = 6;

    /** @var array<string, array{0: float, 1: float}> field => [min, max] */
    private const array RANGES = [
        'weight_kg' => [0.5, 500],
        'sets' => [1, 50],
        'reps' => [1, 255],
        'duration_seconds' => [1, 86_400],
        'distance_meters' => [1, 300_000],
    ];

    /** @var array<string, list<string>> field => units a reply may name */
    private const array UNITS = [
        'weight_kg' => ['', 'kg', 'kgs', 'kilo', 'kilos'],
        'sets' => ['', 'set', 'sets', 'x'],
        'reps' => ['', 'rep', 'reps', 'x', 'times'],
        'duration_seconds' => ['', 'm', 'min', 'mins', 'minute', 'minutes', 's', 'sec', 'secs', 'second', 'seconds'],
        'distance_meters' => ['', 'm', 'meter', 'meters', 'metre', 'metres', 'k', 'km'],
    ];

    private const string FIELD = '/^exercises\.(\d+)\.(?:set_list\.(\d+)\.)?(weight_kg|sets|reps|duration_seconds|distance_meters)$/';

    public function openFor(User $user): ?ActivityLog
    {
        $log = $user->activityLogs()
            ->where('created_at', '>=', now()->subHours(self::OPEN_HOURS))
            ->first();

        return $log !== null && ! empty($log->questions) ? $log : null;
    }

    /**
     * The updated entry, or null when the reply does not answer the question.
     */
    public function answer(ActivityLog $log, NumberReply $reply): ?ExerciseEntry
    {
        if (! preg_match(self::FIELD, (string) ($log->questions[0]['field'] ?? ''), $target)) {
            return null;
        }

        [, $entryIndex, $setIndex, $column] = $target;

        if (! in_array($reply->unit, self::UNITS[$column], true)) {
            return null;
        }

        $value = $this->toColumnUnit($column, $reply->value, $reply->unit);
        [$min, $max] = self::RANGES[$column];

        $entry = $log->exerciseEntries()->orderBy('sort_order')->skip((int) $entryIndex)->first();

        if ($entry === null || $value < $min) {
            return null;
        }

        $value = min($value, $max);
        $value = $column === 'weight_kg' ? $value : (int) round($value);

        if ($setIndex !== '') {
            return $this->answerOneSet($log, $entry, (int) $setIndex + 1, $column, $value);
        }

        $changes = [$column => $value];

        $pair = $log->questions[0]['pair'] ?? null;

        if ($column === 'sets' && is_array($pair)) {
            if (! in_array((int) round($value), $pair, true)) {
                return null;
            }

            $changes['reps'] = (int) round($value) === $pair[0] ? $pair[1] : $pair[0];
        }

        $entry->update($changes);
        $this->syncSets($entry, $changes);
        $log->update(['questions' => null]);

        return $entry;
    }

    /**
     * An answered reps or weight fills the sets that lack it; an answered
     * number of sets (or a sets-or-reps pair) rebuilds them as equal sets.
     *
     * @param  array<string, int|float>  $changes
     */
    private function syncSets(ExerciseEntry $entry, array $changes): void
    {
        if (array_key_exists('sets', $changes)) {
            $entry->replaceSets(array_fill(0, (int) $entry->sets, ['reps' => $entry->reps, 'weight_kg' => $entry->weight_kg === null ? null : (float) $entry->weight_kg]));

            return;
        }

        foreach (['reps', 'weight_kg'] as $column) {
            if (array_key_exists($column, $changes)) {
                $entry->fillMissingOnSets($column, $changes[$column]);
            }
        }

        if (! $entry->setRows()->exists() && $entry->sets !== null) {
            $entry->replaceSets(array_fill(0, (int) $entry->sets, ['reps' => $entry->reps, 'weight_kg' => $entry->weight_kg === null ? null : (float) $entry->weight_kg]));
        }
    }

    private function answerOneSet(ActivityLog $log, ExerciseEntry $entry, int $position, string $column, int|float $value): ?ExerciseEntry
    {
        if (! in_array($column, ['weight_kg', 'reps'], true) || ! $entry->updateSet($position, $column, $value)) {
            return null;
        }

        $log->update(['questions' => null]);

        return $entry;
    }

    public function skip(ActivityLog $log): void
    {
        $log->update(['questions' => null]);
    }

    /**
     * A bare number for a time means minutes; for a distance under 100, kilometers.
     */
    private function toColumnUnit(string $column, float $value, string $unit): float
    {
        return match (true) {
            $column === 'duration_seconds' && ! in_array($unit, ['s', 'sec', 'secs', 'seconds'], true) => $value * 60,
            $column === 'distance_meters' && in_array($unit, ['k', 'km'], true) => $value * 1000,
            $column === 'distance_meters' && $unit === '' && $value < 100 => $value * 1000,
            default => $value,
        };
    }
}
