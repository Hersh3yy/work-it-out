<?php

declare(strict_types=1);

namespace App\Profile;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * When a person's saved data last changed, and so when their session ends:
 * profile.rolling.session_gap_minutes after the last change. The written
 * profile is rewritten once per session instead of after every burst.
 */
final class ProfileChanges
{
    private const string KEY = 'profile:changed-at:';

    public static function markNow(User $user): void
    {
        Cache::put(self::KEY.$user->id, now()->toIso8601String(), now()->addDays(2));
    }

    public static function lastAt(User $user): ?CarbonImmutable
    {
        $at = Cache::get(self::KEY.$user->id);

        return is_string($at) ? CarbonImmutable::parse($at) : null;
    }

    public static function sessionEndAfter(CarbonImmutable $change): CarbonImmutable
    {
        return $change->addMinutes((int) config('profile.rolling.session_gap_minutes'));
    }
}
