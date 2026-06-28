<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\WorkoutSession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin WorkoutSession */
final class WorkoutSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'logged_at'         => $this->logged_at->toIso8601String(),
            'duration_minutes'  => $this->duration_minutes,
            'perceived_exertion' => $this->perceived_exertion,
            'energy_level'      => $this->energy_level,
            'notes'             => $this->notes,
            'completed_planned' => $this->completed_planned,
            'total_volume_kg'   => $this->totalVolumeKg,
            'exercises'         => ExerciseEntryResource::collection(
                $this->whenLoaded('exerciseEntries')
            ),
            'created_at'        => $this->created_at?->toIso8601String(),
            'updated_at'        => $this->updated_at?->toIso8601String(),
        ];
    }
}
