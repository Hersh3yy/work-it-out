<?php

declare(strict_types=1);

namespace App\Ai\Usage;

/**
 * What a call cost in USD, from config ai.prices (per million tokens).
 */
final class ModelPrice
{
    /** Null when the model has no price in config: nothing is guessed. */
    public static function cost(?string $model, int $inputTokens, int $outputTokens): ?float
    {
        $price = config('ai.prices')[$model ?? ''] ?? null;

        if (! is_array($price) || ! is_numeric($price['input'] ?? null) || ! is_numeric($price['output'] ?? null)) {
            return null;
        }

        return $inputTokens / 1_000_000 * (float) $price['input']
            + $outputTokens / 1_000_000 * (float) $price['output'];
    }
}
