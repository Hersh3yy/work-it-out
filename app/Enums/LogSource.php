<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The door a free-text log came in through.
 */
enum LogSource: string
{
    case Http = 'http';
    case Telegram = 'telegram';
    case Simulate = 'simulate';

    /**
     * The source for a chat provider name; unknown providers count as HTTP.
     */
    public static function fromProvider(string $provider): self
    {
        return self::tryFrom($provider) ?? self::Http;
    }
}
