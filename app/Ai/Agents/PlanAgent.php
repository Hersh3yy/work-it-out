<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Enums\PlanType;
use App\Enums\TrainerPersona;
use App\Models\User;
use App\Services\Profile\ProfileIntakeReport;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;

/**
 * Generates a personalised weekly workout or meal plan.
 *
 * The plan type (workout vs. meal) is injected at construction time so the
 * same agent class covers both endpoints without branching at the call site.
 *
 * @see https://refactoring.guru/design-patterns/strategy
 */
final class PlanAgent implements Agent
{
    use Promptable;

    /**
     * @param  array<string, mixed>  $personalRecords
     */
    public function __construct(
        private readonly User $user,
        private readonly PlanType $planType,
        private readonly array $personalRecords = [],
        private readonly ?ProfileIntakeReport $intake = null,
    ) {}

    public function instructions(): string
    {
        $profile = json_encode([
            'name' => $this->user->name,
            'experience_level' => $this->user->experience_level?->value,
            'primary_goal' => $this->user->primary_goal?->value,
            'goal_description' => $this->user->goal_description,
            'training_days' => $this->user->training_days_per_week,
            'current_weight_kg' => $this->user->current_weight_kg,
            'target_weight_kg' => $this->user->target_weight_kg,
            'personal_records' => $this->personalRecords,
        ], JSON_PRETTY_PRINT);

        $profile .= $this->intakeNotes();

        $coach = $this->user->trainer_persona;

        return match ($this->planType) {
            PlanType::Workout => $this->workoutInstructions($profile, $coach),
            PlanType::Meal => $this->mealInstructions($profile),
        };
    }

    /**
     * When the profile has gaps, tell the model to state its assumptions
     * instead of silently guessing.
     */
    private function intakeNotes(): string
    {
        if ($this->intake === null || $this->intake->isComplete()) {
            return '';
        }

        $missing = implode(', ', array_keys($this->intake->missing));

        return "\n\nNOTE: The following profile fields are missing: {$missing}. "
            .'Where a missing field affects the plan, choose a sensible default and state the assumption explicitly at the top of the plan.';
    }

    private function workoutInstructions(string $profile, TrainerPersona $coach): string
    {
        $coachStyle = match ($coach) {
            TrainerPersona::LtSurge => 'Write this as a strict military training order from Lt. Surge. Use markdown with bold headers and mission-style language.',
            TrainerPersona::Shen => 'Write this as a performance training programme from Shen. Use markdown with clean headers. Include heart-rate zones and progressive loading notes.',
            TrainerPersona::Latika => 'Write this as a holistic weekly movement practice from Latika. Use markdown. Balance intensity with recovery and mention breathing/mindfulness cues.',
        };

        return <<<INSTRUCTIONS
You are an expert strength and conditioning coach generating a personalised weekly workout plan.

User profile:
{$profile}

{$coachStyle}

Requirements:
- 7-day programme matching the user's training_days
- Rest days should include active recovery suggestions
- Each training day: specific exercises, sets, reps, tempo or duration
- Include warm-up and cool-down guidance
- Tailor intensity to experience_level and primary_goal
- Use markdown format, suitable for rendering in a mobile app
- Max 600 words
INSTRUCTIONS;
    }

    private function mealInstructions(string $profile): string
    {
        return <<<INSTRUCTIONS
You are Latika, a holistic nutritionist. Generate a personalised 7-day meal plan.

User profile:
{$profile}

Requirements:
- Cover breakfast, lunch, dinner, and snacks for each day
- Respect the user's primary_goal (e.g. fat loss, muscle building, endurance)
- Provide estimated macros (protein/carbs/fat/calories) per day
- Suggest whole foods; avoid processed options
- Include hydration guidance
- Write with warmth and encouragement, in Latika's voice
- Use markdown format, suitable for rendering in a mobile app
- Max 700 words
INSTRUCTIONS;
    }
}
