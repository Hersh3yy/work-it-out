<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\ExperienceLevel;
use App\Enums\PrimaryGoal;
use App\Enums\TrainerPersona;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateProfileTrainerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'trainer_persona'        => ['sometimes', Rule::enum(TrainerPersona::class)],
            'experience_level'       => ['sometimes', Rule::enum(ExperienceLevel::class)],
            'training_days_per_week' => ['sometimes', 'integer', 'min:1', 'max:7'],
            'primary_goal'           => ['sometimes', Rule::enum(PrimaryGoal::class)],
            'goal_description'       => ['nullable', 'string', 'max:1000'],
            'goal_deadline'          => ['nullable', 'date', 'after:today'],
            'target_weight_kg'       => ['nullable', 'numeric', 'min:20', 'max:500'],
        ];
    }
}
