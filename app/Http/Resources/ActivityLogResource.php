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
    private const string ASKED_FIELD = '/^exercises\.(\d+)\.(?:set_list\.\d+\.)?(\w+)$/';

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        [$askedIndex, $askedColumn] = $this->askedFor();

        return [
            'id' => $this->id,
            'source' => $this->source->value,
            'log_type' => $this->log_type->value,
            'message' => $this->raw_message,
            'summary' => $this->summary,
            'logged_on' => $this->logged_on->toDateString(),
            'sent_at' => $this->created_at?->toIso8601String(),
            'open_question' => $this->questions[0]['question'] ?? null,
            'entries' => $this->exerciseEntries->sortBy('sort_order')->values()->map(
                fn (ExerciseEntry $entry, int $index): ExerciseEntryResource => new ExerciseEntryResource($entry, $index === $askedIndex ? $askedColumn : null),
            )->all(),
            'limitation' => $this->whenLoaded('limitation', fn (): ?LimitationResource => $this->limitation === null ? null : new LimitationResource($this->limitation)),
        ];
    }

    /**
     * Which entry and value the open question is about ("exercises.1.weight_kg").
     *
     * @return array{0: int|null, 1: string|null}
     */
    private function askedFor(): array
    {
        return preg_match(self::ASKED_FIELD, (string) ($this->questions[0]['field'] ?? ''), $m)
            ? [(int) $m[1], $m[2]]
            : [null, null];
    }
}
