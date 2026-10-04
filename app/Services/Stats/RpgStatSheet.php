<?php

declare(strict_types=1);

namespace App\Services\Stats;

use App\Contracts\Stats\StatSheet;
use App\Http\Resources\CustomRpgStatResource;
use App\Models\User;

/**
 * The current stat sheet: RPG core stats from the user's columns plus custom stats.
 */
final class RpgStatSheet implements StatSheet
{
    public function for(User $user): array
    {
        return [
            'rpg' => [
                'strength' => (int) ($user->rpg_strength ?? 1),
                'stamina' => (int) ($user->rpg_stamina ?? 1),
                'vitality' => (int) ($user->rpg_vitality ?? 1),
            ],
            'custom_stats' => CustomRpgStatResource::collection($user->customRpgStats)->resolve(),
        ];
    }
}
