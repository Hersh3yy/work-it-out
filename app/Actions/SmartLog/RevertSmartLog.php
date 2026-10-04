<?php

declare(strict_types=1);

namespace App\Actions\SmartLog;

use App\Jobs\UpdateUserStats;
use App\Models\ActivityLog;
use App\Models\BodyWeightLog;
use App\Models\NutritionLog;
use App\Models\WorkoutSession;
use Illuminate\Support\Facades\DB;

/**
 * Undoes one free-text log completely: the entries it added (and the session
 * if nothing else is left in it), its body weight or meal, its diary line.
 * Every number is computed from what remains, so nothing else needs restoring.
 */
final readonly class RevertSmartLog
{
    public function handle(ActivityLog $log): void
    {
        $user = $log->user;
        $loggable = $log->loggable;

        DB::transaction(function () use ($log, $loggable, $user): void {
            if ($loggable instanceof WorkoutSession) {
                $log->exerciseEntries()->delete();

                if (! $loggable->exerciseEntries()->exists()) {
                    $loggable->delete();
                }
            }

            if ($loggable instanceof BodyWeightLog) {
                $loggable->delete();
                $latest = $user->bodyWeightLogs()->latest('logged_at')->first();
                $user->update(['current_weight_kg' => $latest?->weight_kg]);
            }

            if ($loggable instanceof NutritionLog) {
                $loggable->delete();
            }

            $log->diaryEntry()->delete();
            $log->delete();
        });

        if ($loggable instanceof WorkoutSession) {
            UpdateUserStats::dispatch($user);
        }
    }
}
