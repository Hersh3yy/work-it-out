<?php

declare(strict_types=1);

namespace App\Actions\Goals;

use App\Events\GoalRecorded;
use App\Interpretation\Rules\GoalNeedsADate;
use App\Models\Goal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/** The answer to "When is it?": the goal gets its day and the question closes. */
final readonly class SetGoalDate
{
    public function handle(Goal $goal, CarbonImmutable $day): void
    {
        DB::transaction(static function () use ($goal, $day): void {
            $goal->update(['target_on' => $day->toDateString()]);
            $log = $goal->activityLog;

            if ($log !== null) {
                $left = array_values(array_filter($log->questions ?? [], static fn (array $q): bool => $q['field'] !== GoalNeedsADate::FIELD));
                $log->update(['questions' => $left ?: null]);
            }
        });

        GoalRecorded::dispatch($goal);
    }
}
