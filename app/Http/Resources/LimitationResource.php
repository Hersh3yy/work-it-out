<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Limitation;
use App\Models\LimitationUpdate;
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
            'red_flags' => $this->red_flags,
            'day' => $this->day(),
            'note' => $this->note,
            'started_on' => $this->started_on->toDateString(),
            'expected_until' => $this->expected_until?->toDateString(),
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'active' => $this->resolved_at === null,
            'activity_log_id' => $this->activity_log_id,
            'updates' => $this->whenLoaded('updates', fn (): array => $this->updates->map(static fn (LimitationUpdate $u): array => [
                'id' => $u->id,
                'note' => $u->note,
                'logged_on' => $u->logged_on->toDateString(),
                'activity_log_id' => $u->activity_log_id,
            ])->values()->all()),
        ];
    }
}
