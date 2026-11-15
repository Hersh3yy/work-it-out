<?php

declare(strict_types=1);

namespace App\Actions\Limitations;

use App\Enums\BodyArea;
use App\Events\LogRecorded;
use App\Limitations\RedFlagScreen;
use App\Models\ActivityLog;
use App\Models\Limitation;
use Illuminate\Support\Facades\DB;

/**
 * The answer to "Where exactly?": the area on the limitation, the log's
 * summary and diary line, and the red-flag screen as the next question, in
 * one transaction; LogRecorded after it commits.
 */
final readonly class SetLimitationArea
{
    public function handle(ActivityLog $log, Limitation $limitation, BodyArea $area): void
    {
        DB::transaction(function () use ($log, $limitation, $area): void {
            $limitation->update(['body_area' => $area]);
            $log->update([
                'questions' => [RedFlagScreen::question()],
                'summary' => 'Limitation: '.$area->label().', '.$limitation->note,
            ]);
            $log->diaryEntry()->update(['content' => $log->summary]);
        });

        LogRecorded::dispatch($log);
    }
}
