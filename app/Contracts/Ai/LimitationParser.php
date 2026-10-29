<?php

declare(strict_types=1);

namespace App\Contracts\Ai;

use App\Exceptions\AiUnavailable;

/**
 * Port for reading an injury or limitation out of a message about the user
 * ("hurt my back, need to go slow for 2 weeks").
 *
 * Returns facts only: whether the message states a limitation at all, the
 * body area from the fixed list (or a few candidates when unclear), the
 * severity when stated, how long it is expected to last, when it started.
 * Nothing medical: no diagnosis, no advice. Plain code decides what to do.
 *
 * @see https://refactoring.guru/design-patterns/adapter
 */
interface LimitationParser
{
    /**
     * @return array{
     *     is_limitation: bool,
     *     body_area: string|null,
     *     candidates: list<string>,
     *     severity: string|null,
     *     duration_days: int|null,
     *     started_days_ago: int|null,
     *     note: string
     * }
     *
     * @throws AiUnavailable
     */
    public function parse(string $text): array;
}
