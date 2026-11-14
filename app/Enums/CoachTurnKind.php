<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Who started a coach turn: the person asked, or the coach wrote first
 * (the morning message).
 */
enum CoachTurnKind: string
{
    case Asked = 'asked';
    case Daily = 'daily';
}
