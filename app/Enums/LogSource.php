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
    case WhatsApp = 'whatsapp';
    case Simulate = 'simulate';
}
