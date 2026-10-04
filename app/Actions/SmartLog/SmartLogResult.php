<?php

declare(strict_types=1);

namespace App\Actions\SmartLog;

use App\Models\ActivityLog;
use App\Models\BodyWeightLog;
use App\Models\DiaryEntry;
use App\Models\ExerciseEntry;
use App\Models\WorkoutSession;

/**
 * What one free-text log produced: the receipt both doors (HTTP, chat) render.
 */
final readonly class SmartLogResult
{
    /**
     * @param  list<ExerciseEntry>  $entries  only the entries this log added
     */
    public function __construct(
        public ActivityLog $log,
        public DiaryEntry $diary,
        public ?WorkoutSession $session = null,
        public bool $addedToExisting = false,
        public array $entries = [],
        public ?BodyWeightLog $weight = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'log_id' => $this->log->id,
            'log_type' => $this->log->log_type->value,
            'summary' => $this->log->summary,
            'logged_on' => $this->log->logged_on->toDateString(),
            'session_id' => $this->session?->id,
            'added_to_existing' => $this->addedToExisting,
            'entries' => array_map(static fn (ExerciseEntry $entry): array => [
                'id' => $entry->id,
                'exercise_name' => $entry->exercise_name,
                'sets' => $entry->sets,
                'reps' => $entry->reps,
                'weight_kg' => $entry->weight_kg === null ? null : (float) $entry->weight_kg,
                'duration_seconds' => $entry->duration_seconds,
                'distance_meters' => $entry->distance_meters,
            ], $this->entries),
            'weight_kg' => $this->weight === null ? null : (float) $this->weight->weight_kg,
            'questions' => $this->log->questions ?? [],
            'diary' => [
                'id' => $this->diary->id,
                'content' => $this->diary->content,
            ],
        ];
    }
}
