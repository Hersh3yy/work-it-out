<?php

declare(strict_types=1);

namespace App\Ai;

use App\Ai\Agents\LimitationAgent;
use App\Contracts\Ai\LimitationParser;
use App\Enums\BodyArea;
use App\Enums\LimitationSeverity;

/**
 * Laravel AI SDK adapter for the LimitationParser port. Runs on the log job's
 * model; the payload is rebuilt from known keys and the fixed lists only.
 */
final readonly class SdkLimitationParser implements LimitationParser
{
    public function parse(string $text): array
    {
        $raw = (new LimitationAgent)->prompt(
            $text,
            provider: config('ai.jobs.log.provider'),
            model: config('ai.jobs.log.model'),
        )->toArray();

        $area = BodyArea::tryFrom((string) ($raw['body_area'] ?? ''));
        $candidates = array_values(array_unique(array_filter(array_map(
            static fn (mixed $c): ?string => BodyArea::tryFrom((string) (is_scalar($c) ? $c : ''))?->value,
            is_array($raw['candidates'] ?? null) ? $raw['candidates'] : [],
        ))));

        $clampDays = static fn (mixed $v): ?int => is_numeric($v) && (int) $v >= 0 && (int) $v <= 365 ? (int) $v : null;

        return [
            'is_limitation' => (bool) ($raw['is_limitation'] ?? false),
            'body_area' => $area?->value,
            'candidates' => array_slice($candidates, 0, 4),
            'severity' => LimitationSeverity::tryFrom((string) ($raw['severity'] ?? ''))?->value,
            'duration_days' => $clampDays($raw['duration_days'] ?? null),
            'started_days_ago' => $clampDays($raw['started_days_ago'] ?? null),
            'note' => mb_substr(trim((string) ($raw['note'] ?? '')), 0, 120),
        ];
    }
}
