<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The chat doors a message can come through. Each one records its logs
 * with its own LogSource.
 */
enum ChatProvider: string
{
    case Telegram = 'telegram';
    case WhatsApp = 'whatsapp';
    case App = 'app';
    case Simulate = 'simulate';

    public function logSource(): LogSource
    {
        return match ($this) {
            self::Telegram => LogSource::Telegram,
            self::WhatsApp => LogSource::WhatsApp,
            self::App => LogSource::Http,
            self::Simulate => LogSource::Simulate,
        };
    }
}
