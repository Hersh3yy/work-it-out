<?php

declare(strict_types=1);

namespace App\Limitations;

use App\Enums\BodyArea;
use App\Models\Limitation;
use App\Models\User;

/**
 * Is this message about a limitation we already have? "Back is 70% of
 * yesterday" with an active lower-back limitation is an update, not a new
 * injury. Same area, or no clear area while exactly one is active.
 */
final class ExistingLimitation
{
    /**
     * @param  array<string, mixed>  $facts  from the LimitationParser
     */
    public static function for(User $user, array $facts): ?Limitation
    {
        $active = $user->limitations()->active()->latest('started_on')->get();
        $area = BodyArea::tryFrom((string) ($facts['body_area'] ?? ''));

        if ($area !== null && $area !== BodyArea::Other) {
            return $active->first(static fn (Limitation $l): bool => $l->body_area === $area);
        }

        $candidates = array_filter(array_map(static fn (mixed $v): ?BodyArea => BodyArea::tryFrom((string) $v), $facts['candidates'] ?? []));
        $matching = $candidates === []
            ? $active
            : $active->filter(static fn (Limitation $l): bool => $l->body_area === null || in_array($l->body_area, $candidates, true));

        return $matching->count() === 1 ? $matching->first() : null;
    }
}
