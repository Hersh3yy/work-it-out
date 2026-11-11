<?php

declare(strict_types=1);

namespace App\Coach;

use App\Models\AiCallRecord;
use App\Models\User;

/**
 * Whether one person may still get coach answers this month
 * (config ai.monthly_tokens_per_user, 0 is no cap). Logging is never capped.
 */
final readonly class CoachBudget
{
    public function exhaustedFor(User $user): bool
    {
        $cap = (int) config('ai.monthly_tokens_per_user');

        if ($cap <= 0) {
            return false;
        }

        $calls = AiCallRecord::query()->where('user_id', $user->id)->since(now()->startOfMonth());

        return (int) $calls->clone()->sum('input_tokens') + (int) $calls->clone()->sum('output_tokens') >= $cap;
    }
}
