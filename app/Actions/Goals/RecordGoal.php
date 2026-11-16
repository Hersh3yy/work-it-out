<?php

declare(strict_types=1);

namespace App\Actions\Goals;

use App\Events\GoalRecorded;
use App\Goals\GoalReading;
use App\Models\ActivityLog;
use App\Models\Goal;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Saves a goal read from a message, tied to that message so /undo removes it. */
final readonly class RecordGoal
{
    public function handle(User $user, ActivityLog $log, GoalReading $reading): Goal
    {
        $goal = DB::transaction(static fn (): Goal => $user->goals()->create([
            'activity_log_id' => $log->id,
            'kind' => $reading->kind,
            'name' => $reading->name,
            'description' => $reading->description,
            'target_on' => $reading->targetOn?->toDateString(),
        ]));

        GoalRecorded::dispatch($goal);

        return $goal;
    }
}
