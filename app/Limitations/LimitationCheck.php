<?php

declare(strict_types=1);

namespace App\Limitations;

use App\Models\ExerciseEntry;
use App\Models\User;

/**
 * One line added to a receipt when a logged exercise loads an area with an
 * active limitation: "Lower back OK with the Deadlift?" (plain code, a
 * question, never advice).
 */
final readonly class LimitationCheck
{
    /**
     * @param  list<ExerciseEntry>  $entries
     */
    public function for(User $user, array $entries): ?string
    {
        $active = $user->limitations()->active()->whereNotNull('body_area')->get();

        if ($active->isEmpty() || $entries === []) {
            return null;
        }

        foreach ($active as $limitation) {
            $loading = array_values(array_filter(
                $entries,
                static fn (ExerciseEntry $entry): bool => ExerciseLoads::loads($entry->exercise_name, $limitation->body_area),
            ));

            if ($loading !== []) {
                $names = array_unique(array_map(static fn (ExerciseEntry $e): string => $e->exercise_name, $loading));

                return ucfirst($limitation->areaLabel()).' OK with the '.implode(' and ', $names).'? Say /better when it has healed.';
            }
        }

        return null;
    }
}
