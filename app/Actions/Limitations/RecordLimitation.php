<?php

declare(strict_types=1);

namespace App\Actions\Limitations;

use App\Enums\BodyArea;
use App\Enums\LimitationSeverity;
use App\Enums\LogSource;
use App\Enums\LogType;
use App\Events\LogRecorded;
use App\Models\Limitation;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Saves a limitation the user stated, all or nothing: the message as an
 * activity log (so it can be undone and cited), the limitation itself, and a
 * diary line. When the body area is unclear the log carries a numbered
 * question with the parser's candidates; the answer fills the area in.
 *
 * Fires LogRecorded after commit, so the written profile picks it up.
 */
final readonly class RecordLimitation
{
    /**
     * @param  array{is_limitation: bool, body_area: string|null, candidates: list<string>, severity: string|null, duration_days: int|null, started_days_ago: int|null, note: string}  $facts
     */
    public function handle(User $user, string $message, array $facts, LogSource $source = LogSource::Http): Limitation
    {
        $area = BodyArea::tryFrom((string) ($facts['body_area'] ?? ''));
        $candidates = $area === null ? $this->candidates($facts['candidates'] ?? []) : [];
        $startedOn = CarbonImmutable::today()->subDays((int) ($facts['started_days_ago'] ?? 0));
        $until = ($facts['duration_days'] ?? null) === null ? null : $startedOn->addDays((int) $facts['duration_days']);
        $note = trim((string) ($facts['note'] ?? '')) ?: mb_substr($message, 0, 120);

        $limitation = DB::transaction(function () use ($user, $message, $source, $area, $candidates, $startedOn, $until, $note, $facts): Limitation {
            $log = $user->activityLogs()->create([
                'source' => $source,
                'log_type' => LogType::General,
                'raw_message' => $message,
                'summary' => 'Limitation: '.($area?->label() ?? 'area to confirm').', '.$note,
                'questions' => $candidates === [] ? null : [[
                    'field' => 'limitation.body_area',
                    'question' => $this->question($candidates),
                    'options' => array_map(static fn (BodyArea $c): string => $c->value, $candidates),
                ]],
                'logged_on' => today()->toDateString(),
            ]);

            $limitation = $user->limitations()->create([
                'activity_log_id' => $log->id,
                'body_area' => $area,
                'severity' => LimitationSeverity::tryFrom((string) ($facts['severity'] ?? '')),
                'note' => $note,
                'started_on' => $startedOn->toDateString(),
                'expected_until' => $until?->toDateString(),
            ]);

            $log->diaryEntry()->create(['user_id' => $user->id, 'content' => $log->summary]);

            return $limitation->setRelation('activityLog', $log);
        });

        LogRecorded::dispatch($limitation->activityLog);

        return $limitation;
    }

    /**
     * The parser's guesses plus "other", at most four.
     *
     * @param  list<string>  $values
     * @return list<BodyArea>
     */
    private function candidates(array $values): array
    {
        $areas = [];

        foreach ($values as $value) {
            $area = BodyArea::tryFrom((string) $value);

            if ($area !== null && $area !== BodyArea::Other && ! in_array($area, $areas, true)) {
                $areas[] = $area;
            }
        }

        $areas = array_slice($areas ?: [BodyArea::LowerBack, BodyArea::UpperBack, BodyArea::Shoulder], 0, 3);
        $areas[] = BodyArea::Other;

        return $areas;
    }

    /**
     * @param  list<BodyArea>  $candidates
     */
    private function question(array $candidates): string
    {
        $options = [];

        foreach ($candidates as $i => $area) {
            $options[] = ($i + 1).') '.$area->label();
        }

        return 'Noted, I\'ll keep that in mind. Where exactly? '.implode(' ', $options);
    }
}
