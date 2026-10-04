<?php

declare(strict_types=1);

namespace App\Contracts\Stats;

use App\Models\User;

/**
 * Port for the user's stat sheet: the one place the game layer lives.
 *
 * Today that is the RPG sheet (Strength, Stamina, Vitality plus custom
 * stats). It is an open question whether RPG stays or becomes real per-area
 * stats (chest strength, leg strength, run score), so every reader goes
 * through this port and a replacement is one new adapter
 * (PLAN.md section 4, 2026-10-04). Logging never writes to it.
 */
interface StatSheet
{
    /**
     * Keys merged into stats-bearing responses (dashboard, /api/stats).
     *
     * @return array<string, mixed>
     */
    public function for(User $user): array;
}
