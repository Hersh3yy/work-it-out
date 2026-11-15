<?php

declare(strict_types=1);

namespace App\Actions\Limitations;

use App\Events\LogRecorded;
use App\Models\ActivityLog;
use App\Models\Limitation;
use Illuminate\Support\Facades\DB;

/**
 * The red-flag screen's answer on the limitation, and the question closed,
 * in one transaction; LogRecorded after it commits.
 */
final readonly class AnswerRedFlagScreen
{
    /**
     * @param  string  $result  Limitation::RED_FLAGS_CLEAR or RED_FLAGS_FLAGGED
     */
    public function handle(ActivityLog $log, Limitation $limitation, string $result): void
    {
        DB::transaction(function () use ($log, $limitation, $result): void {
            $limitation->update(['red_flags' => $result]);
            $log->update(['questions' => null]);
        });

        LogRecorded::dispatch($log);
    }
}
