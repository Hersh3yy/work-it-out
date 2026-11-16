<?php

declare(strict_types=1);

namespace App\Conversation\Answers;

use App\Actions\Limitations\RecordLimitation;
use App\Interpretation\Rules\GoalNeedsADate;
use App\Limitations\RedFlagScreen;

/**
 * What a log's open question asks for, read once from its field so no
 * handler has to compare field strings.
 */
enum OpenQuestionKind
{
    case ExerciseValue;
    case LimitationArea;
    case RedFlagCheck;
    case GoalDate;

    public static function forField(string $field): ?self
    {
        return match (true) {
            str_starts_with($field, 'exercises.') => self::ExerciseValue,
            $field === RecordLimitation::AREA_QUESTION_FIELD => self::LimitationArea,
            $field === RedFlagScreen::FIELD => self::RedFlagCheck,
            $field === GoalNeedsADate::FIELD => self::GoalDate,
            default => null,
        };
    }

    /**
     * Once the coach has spoken, a "yes" or "no" answers the coach's own
     * closing question ("knee OK on stairs?"), never the red-flag screen.
     */
    public function closesWhenTheCoachSpeaks(): bool
    {
        return $this === self::RedFlagCheck;
    }
}
