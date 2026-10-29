<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Where a limitation sits. A fixed list, so suggestions can be filtered by
 * plain code and the bot can offer numbered choices when a message is unclear.
 */
enum BodyArea: string
{
    case Neck = 'neck';
    case Shoulder = 'shoulder';
    case Elbow = 'elbow';
    case Wrist = 'wrist';
    case Chest = 'chest';
    case UpperBack = 'upper_back';
    case LowerBack = 'lower_back';
    case Hip = 'hip';
    case Knee = 'knee';
    case Ankle = 'ankle';
    case Foot = 'foot';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::UpperBack => 'upper back',
            self::LowerBack => 'lower back',
            default => $this->value,
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
