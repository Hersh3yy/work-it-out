<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Limitation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Limitation */
final class LimitationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'body_area' => $this->body_area?->value,
            'area_label' => $this->areaLabel(),
            'severity' => $this->severity?->value,
            'note' => $this->note,
            'started_on' => $this->started_on->toDateString(),
            'expected_until' => $this->expected_until?->toDateString(),
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'active' => $this->resolved_at === null,
            'activity_log_id' => $this->activity_log_id,
        ];
    }
}
