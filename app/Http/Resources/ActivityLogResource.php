<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ActivityLog;
use App\Models\ExerciseEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One message and what it produced: the user's words, what was saved, the
 * sets, an open question, a limitation. The app's timeline is built from this.
 *
 * @mixin ActivityLog
 */
final class ActivityLogResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'source' => $this->source->value,
            'log_type' => $this->log_type->value,
            'message' => $this->raw_message,
            'summary' => $this->summary,
            'logged_on' => $this->logged_on->toDateString(),
            'sent_at' => $this->created_at?->toIso8601String(),
            'open_question' => $this->questions[0]['question'] ?? null,
            'entries' => $this->exerciseEntries->sortBy('sort_order')->values()->map(fn (ExerciseEntry $entry): array => [
                'id' => $entry->id,
                'exercise_name' => $entry->exercise_name,
                'sets' => $entry->sets,
                'reps' => $entry->reps,
                'weight_kg' => $entry->weight_kg === null ? null : (float) $entry->weight_kg,
                'duration_seconds' => $entry->duration_seconds,
                'distance_meters' => $entry->distance_meters,
                'volume_kg' => $entry->volumeKg(),
                'set_list' => $entry->setRows->map(fn ($set): array => [
                    'reps' => $set->reps,
                    'weight_kg' => $set->weight_kg === null ? null : (float) $set->weight_kg,
                ])->all(),
            ])->all(),
            'limitation' => $this->whenLoaded('limitation', fn (): ?LimitationResource => $this->limitation === null ? null : new LimitationResource($this->limitation)),
        ];
    }
}
