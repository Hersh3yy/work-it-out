<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
final class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                     => $this->id,
            'name'                   => $this->name,
            'email'                  => $this->email,
            'trainer_persona'        => $this->trainer_persona,
            'experience_level'       => $this->experience_level,
            'training_days_per_week' => $this->training_days_per_week,
            'primary_goal'           => $this->primary_goal,
            'goal_description'       => $this->goal_description,
            'goal_deadline'          => $this->goal_deadline?->toDateString(),
            'target_weight_kg'       => $this->target_weight_kg,
            'current_weight_kg'      => $this->current_weight_kg,
            'weekly_adherence_rate'  => $this->weekly_adherence_rate,
            'current_streak_days'    => $this->current_streak_days,
            'last_active_at'         => $this->last_active_at?->toIso8601String(),
            'rpg'                    => [
                'strength' => (int) ($this->rpg_strength ?? 1),
                'stamina'  => (int) ($this->rpg_stamina  ?? 1),
                'vitality' => (int) ($this->rpg_vitality ?? 1),
            ],
            'created_at'             => $this->created_at?->toIso8601String(),
        ];
    }
}
