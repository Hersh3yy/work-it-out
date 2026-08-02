<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\CustomRpgStat;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CustomRpgStat */
final class CustomRpgStatResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'value' => (int) $this->value,
            'level' => (int) $this->level,
            'unit' => $this->unit,
            'category' => $this->category,
            'change_reason' => $this->change_reason,
            'last_updated_at' => $this->last_updated_at?->toIso8601String(),
        ];
    }
}
