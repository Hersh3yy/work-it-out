<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown by any AI adapter when the provider fails or returns an unusable payload.
 *
 * Carries only the agent class and a short reason: never the user's message,
 * never the raw payload, never a secret. Controllers map it to a 503.
 */
final class AiUnavailable extends RuntimeException
{
    public static function because(string $agent, string $reason): self
    {
        return new self("{$agent}: {$reason}");
    }
}
