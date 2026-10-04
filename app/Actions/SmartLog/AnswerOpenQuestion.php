<?php

declare(strict_types=1);

namespace App\Actions\SmartLog;

use App\Models\ActivityLog;
use App\Models\ExerciseEntry;
use App\Models\User;

/**
 * Fills in the value a log's open question asked for, without AI.
 *
 * The question names its field ("exercises.1.weight_kg"), so a reply like
 * "30", "32.5 kg", "5k" or "28 min" can be written straight into that entry.
 * Only the user's latest log, and only for six hours.
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

    public function openFor(User $user): ?ActivityLog
    {
        $log = $user->activityLogs()
            ->where('created_at', '>=', now()->subHours(self::OPEN_HOURS))
            ->first();

        return $log !== null && ! empty($log->questions) ? $log : null;
    }

    /**
     * The updated entry, or null when the text is not an answer to the question.
     */
    public function answer(ActivityLog $log, string $text): ?ExerciseEntry
    {
        $field = (string) ($log->questions[0]['field'] ?? '');

        if (! preg_match('/^exercises\.(\d+)\.(weight_kg|sets|reps|duration_seconds|distance_meters)$/', $field, $target)
            || ! preg_match('/^\s*(\d+(?:[.,]\d+)?)\s*([a-z]*)\s*$/i', $text, $reply)) {
            return null;
        }

        $column = $target[2];
        $value = $this->toColumnUnit($column, (float) str_replace(',', '.', $reply[1]), strtolower($reply[2]));
        [$min, $max] = self::RANGES[$column];

        $entry = $log->exerciseEntries()->orderBy('sort_order')->skip((int) $target[1])->first();

        if ($entry === null || $value < $min) {
            return null;
        }

        $value = min($value, $max);
        $entry->update([$column => $column === 'weight_kg' ? $value : (int) round($value)]);
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
