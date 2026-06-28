<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ExerciseEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ExerciseEntry */
final class ExerciseEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'exercise_name'    => $this->exercise_name,
            'sets'             => $this->sets,
            'reps'             => $this->reps,
            'weight_kg'        => $this->weight_kg,
            'duration_seconds' => $this->duration_seconds,
            'distance_meters'  => $this->distance_meters,
            'notes'            => $this->notes,
            'sort_order'       => $this->sort_order,
        ];
    }
}
