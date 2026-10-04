<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What a free-text log turned out to be. The parser's schema, the
 * normalizer, the recorder and the receipt all read this one list.
 */
enum LogType: string
{
    case Workout = 'workout';
    case Biometrics = 'biometrics';
    case Meal = 'meal';
    case General = 'general';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Whether recording or removing this log changes training stats.
     */
    public function touchesTraining(): bool
    {
        return $this === self::Workout;
    }
}
