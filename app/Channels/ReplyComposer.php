<?php

declare(strict_types=1);

namespace App\Channels;

use App\Actions\SmartLog\SmartLogResult;
use App\Enums\LogType;
use App\Models\ExerciseEntry;

/**
 * Plain-text chat replies: the receipt for a log and one line per entry.
 */
final class ReplyComposer
{
    public function receipt(SmartLogResult $result): string
    {
        $log = $result->log;

        $lines = [match ($log->log_type) {
            LogType::Workout => ($result->addedToExisting ? "Added to today's session: " : 'Logged: ')
                .($result->entries === [] ? $log->summary : implode(', ', array_map($this->entry(...), $result->entries))),
            LogType::Biometrics => $result->weight !== null
                ? 'Logged weight: '.self::number($result->weight->weight_kg).' kg'
                : 'Noted: '.$log->summary,
            default => 'Noted: '.$log->summary,
        }];

        foreach ($log->questions ?? [] as $question) {
            $lines[] = $question['question'];
        }

        return implode("\n", $lines);
    }

    public function entry(ExerciseEntry $entry): string
    {
        $parts = [$entry->exercise_name];

        $parts[] = match (true) {
            $entry->sets !== null && $entry->reps !== null => "{$entry->sets}x{$entry->reps}",
            $entry->reps !== null => "{$entry->reps} reps",
            $entry->sets !== null => "{$entry->sets} sets",
            default => null,
        };

        if ($entry->weight_kg !== null) {
            $parts[] = '@ '.self::number($entry->weight_kg).' kg';
        }
        if ($entry->distance_meters !== null) {
            $parts[] = self::number($entry->distance_meters / 1000).' km';
        }
        if ($entry->duration_seconds !== null) {
            $parts[] = self::duration((int) $entry->duration_seconds);
        }

        return implode(' ', array_filter($parts));
    }

    private static function number(mixed $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
    }

    private static function duration(int $seconds): string
    {
        if ($seconds % 60 === 0) {
            return intdiv($seconds, 60).' min';
        }

        return sprintf('%d:%02d', intdiv($seconds, 60), $seconds % 60);
    }
}
