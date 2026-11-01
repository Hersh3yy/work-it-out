<?php

declare(strict_types=1);

namespace App\Actions\Limitations;

use App\Enums\LogSource;
use App\Enums\LogType;
use App\Events\LogRecorded;
use App\Models\Limitation;
use App\Models\LimitationUpdate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * How an active limitation is going, in the person's own words, all or
 * nothing: the message as an activity log (undoable, citable), the dated
 * update, a diary line. A new duration ("one more week") moves the expected
 * end. Fires LogRecorded after commit.
 */
final readonly class RecordLimitationUpdate
{
    /**
     * @param  array<string, mixed>  $facts  from the LimitationParser
     */
    public function handle(Limitation $limitation, string $message, array $facts, LogSource $source = LogSource::Http): LimitationUpdate
    {
        $note = mb_strimwidth(trim($message), 0, 200, '…');
        $until = ($facts['duration_days'] ?? null) === null ? null : CarbonImmutable::today()->addDays((int) $facts['duration_days']);

        $update = DB::transaction(function () use ($limitation, $message, $source, $note, $until): LimitationUpdate {
            $log = $limitation->user->activityLogs()->create([
                'source' => $source,
                'log_type' => LogType::General,
                'raw_message' => $message,
                'summary' => 'Update on '.$limitation->areaLabel().': '.$note,
                'logged_on' => today()->toDateString(),
            ]);

            if ($until !== null) {
                $limitation->update(['expected_until' => $until->toDateString()]);
            }

            $log->diaryEntry()->create(['user_id' => $limitation->user_id, 'content' => $log->summary]);

            return $limitation->updates()->create([
                'activity_log_id' => $log->id,
                'note' => $note,
                'logged_on' => today()->toDateString(),
            ])->setRelation('limitation', $limitation);
        });

        LogRecorded::dispatch($limitation->user->activityLogs()->whereKey($update->activity_log_id)->firstOrFail());

        return $update;
    }
}
