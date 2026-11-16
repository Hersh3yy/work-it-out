<?php

declare(strict_types=1);

namespace App\Goals;

use App\Fitness\PowerReading;

/** How far one demanded quality's power is toward its standard. */
enum Preparedness: string
{
    case Ready = 'ready';
    case Close = 'close';
    case NotYet = 'not_yet';
    case NotMeasured = 'not_measured';

    public static function of(?PowerReading $power): self
    {
        return match (true) {
            $power === null => self::NotMeasured,
            $power->value >= (float) config('fitness.goals.ready_at') => self::Ready,
            $power->value >= (float) config('fitness.goals.close_at') => self::Close,
            default => self::NotYet,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Ready => 'ready',
            self::Close => 'close',
            self::NotYet => 'not yet',
            self::NotMeasured => 'not measured yet',
        };
    }
}
