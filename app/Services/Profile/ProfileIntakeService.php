<?php

declare(strict_types=1);

namespace App\Services\Profile;

use App\Contracts\Profile\ProfileIntake;
use App\Models\User;

/**
 * Determines which profile fields are missing and what a coach should ask
 * to fill them — the single source of truth for profile onboarding, used by
 * the intake API endpoint and injected into every trainer prompt.
 */
final class ProfileIntakeService implements ProfileIntake
{
    /**
     * Field => the question a coach should ask to fill it.
     * Ordered by how much each answer improves plan quality.
     */
    private const array FIELD_QUESTIONS = [
        'primary_goal' => 'What is your main goal right now — building muscle, losing fat, improving endurance, or general fitness?',
        'experience_level' => 'How would you rate your training experience — beginner, intermediate, or advanced?',
        'training_days_per_week' => 'How many days per week can you realistically train?',
        'current_weight_kg' => 'What is your current body weight in kg?',
        'goal_description' => 'Describe your goal in your own words — what would success look like for you?',
        'target_weight_kg' => 'Do you have a target weight in kg?',
        'goal_deadline' => 'Do you have a target date for reaching this goal?',
    ];

    public function report(User $user): ProfileIntakeReport
    {
        $missing = [];

        foreach (self::FIELD_QUESTIONS as $field => $question) {
            if ($this->isMissing($user, $field)) {
                $missing[$field] = $question;
            }
        }

        $total = count(self::FIELD_QUESTIONS);
        $percent = (int) round((($total - count($missing)) / $total) * 100);

        return new ProfileIntakeReport($percent, $missing);
    }

    private function isMissing(User $user, string $field): bool
    {
        $value = $user->getAttribute($field);

        if ($value === null || $value === '') {
            return true;
        }

        // training_days_per_week defaults to 0, which is not a usable answer.
        return $field === 'training_days_per_week' && (int) $value === 0;
    }
}
