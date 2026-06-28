<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\NutritionLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin NutritionLog */
final class NutritionLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'logged_at'  => $this->logged_at->toIso8601String(),
            'meal_type'  => $this->meal_type,
            'food_name'  => $this->food_name,
            'calories'   => $this->calories,
            'protein_g'  => $this->protein_g,
            'carbs_g'    => $this->carbs_g,
            'fat_g'      => $this->fat_g,
            'notes'      => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
