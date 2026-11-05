<?php

declare(strict_types=1);

namespace App\Channels;

use App\Actions\SmartLog\SmartLogResult;
use App\Enums\LogType;
use App\Models\ExerciseEntry;
use App\Services\Exercises\ExerciseLine;

/**
 * Plain-text chat replies: the receipt for a log and one line per entry.
 */
final readonly class ReplyComposer
{
    public function __construct(private ExerciseLine $lines) {}

    public function receipt(SmartLogResult $result): string
    {
        $log = $result->log;

        $lines = [match ($log->log_type) {
            LogType::Workout => ($result->addedToExisting ? "Added to today's session: " : 'Logged: ')
                .($result->entries === [] ? $log->summary : implode(', ', array_map($this->entry(...), $result->entries))),
            LogType::Biometrics => $result->weight !== null
                ? 'Logged weight: '.ExerciseLine::number($result->weight->weight_kg).' kg'
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
        return $this->lines->for($entry);
    }
}
