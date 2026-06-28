<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\BodyWeightLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin BodyWeightLog */
final class BodyWeightLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'logged_at'  => $this->logged_at->toDateString(),
            'weight_kg'  => $this->weight_kg,
            'notes'      => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
