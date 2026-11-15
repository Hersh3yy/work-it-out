<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ExerciseEntry;
use App\Models\ExerciseSet;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One saved exercise with its sets. The app reads the set list first: reps
 * at a weight, a time, a distance, an effort. `asked_for` names the value
 * the bot is still asking for on this entry, so the app shows "?" only there.
 *
 * @mixin ExerciseEntry
 */
final class ExerciseEntryResource extends JsonResource
{
    public function __construct(ExerciseEntry $entry, private readonly ?string $askedFor = null)
    {
        parent::__construct($entry);
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'exercise_name' => $this->exercise_name,
            'sets' => $this->sets,
            'reps' => $this->reps,
            'weight_kg' => $this->weight_kg === null ? null : (float) $this->weight_kg,
            'duration_seconds' => $this->duration_seconds,
            'distance_meters' => $this->distance_meters,
            'notes' => $this->notes,
            'bodyweight' => $this->bodyweight,
            'assisted' => $this->assisted,
            'group_label' => $this->group_label,
            'asked_for' => $this->askedFor,
            'volume_kg' => $this->volumeKg(),
            'sort_order' => $this->sort_order,
            'set_list' => $this->setRows->map(fn (ExerciseSet $set): array => $set->values())->all(),
        ];
    }
}
