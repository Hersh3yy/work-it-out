<?php

declare(strict_types=1);

namespace App\Actions\Limitations;

use App\Events\LogRecorded;
use App\Models\Limitation;

/**
 * The user says it is better. Only the user does this; the app never
 * decides a limitation is over. The written profile is refreshed.
 */
final readonly class ResolveLimitation
{
    public function handle(Limitation $limitation): void
    {
        $limitation->update(['resolved_at' => now()]);

        if ($limitation->activityLog !== null) {
            LogRecorded::dispatch($limitation->activityLog);
        }
    }
}
