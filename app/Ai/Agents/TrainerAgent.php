<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Enums\TrainerPersona;
use App\Models\NutritionLog;
use App\Models\User;
use App\Models\WorkoutSession;
use App\Services\Profile\ProfileIntakeReport;
use Illuminate\Support\Stringable;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Promptable;

/**
 * The AI fitness trainer agent.
 *
 * Persona is injected via the User's trainer_persona enum, which provides
 * its own systemPrompt() — following the Strategy pattern so the agent never
 * switches on persona type directly.
 *
 * Personal records and the profile-intake report are passed in by the
 * SdkTrainerChat adapter, keeping this class a pure prompt-builder.
 *
 * Conversation history is persisted by the AI SDK's RemembersConversations
 * trait; no custom ai_conversations table is needed.
 *
 * @see https://refactoring.guru/design-patterns/strategy
 */
final class TrainerAgent implements Agent, Conversational
{
    use Promptable, RemembersConversations;

    /**
     * @param  array<string, mixed>  $personalRecords
     */
    public function __construct(
        private readonly User $user,
        private readonly ?TrainerPersona $coachOverride = null,
        private readonly array $personalRecords = [],
        private readonly ?ProfileIntakeReport $intake = null,
    ) {}

    public function instructions(): Stringable|string
    {
        $persona = $this->coachOverride ?? $this->user->trainer_persona;
        $personaPrompt = $persona->systemPrompt();
        $contextJson = json_encode($this->buildContext(), JSON_PRETTY_PRINT);
        $today = now()->toDateString();
        $intakeRules = $this->intakeRules();

        return <<<INSTRUCTIONS
{$personaPrompt}

---

Here is the user's current data. Use this to make your responses specific, personal, and data-driven. Reference actual numbers when relevant. Never make up data that isn't here.

USER CONTEXT:
{$contextJson}

---

RULES:
- Always respond in character. Never break persona.
- Keep responses under 200 words unless the user asks for a detailed plan.
- Reference specific data from the context (exercise names, weights, adherence rate, streak).
- The personal_records section contains the user's REAL records computed from logged data — quote these exact numbers when discussing progress. Never invent records.
- If the user asks something unrelated to fitness, redirect them back to training. The General does this bluntly. The Coach does it warmly.
- Never diagnose injuries. If the user mentions pain, recommend they see a professional.
- Today's date is {$today}.
{$intakeRules}
INSTRUCTIONS;
    }

    /**
     * When profile fields are missing, instruct the coach to fill exactly
     * one gap per reply so onboarding happens naturally in conversation.
     */
    private function intakeRules(): string
    {
        if ($this->intake === null || $this->intake->isComplete()) {
            return '';
        }

        $questions = implode("\n", array_map(
            static fn (string $field, string $question): string => "  - {$field}: {$question}",
            array_keys($this->intake->missing),
            array_values($this->intake->missing),
        ));

        return <<<RULES
- PROFILE INTAKE: The user's profile is {$this->intake->percentComplete}% complete. The following fields are missing. End your reply with exactly ONE of these questions (rephrased in your own voice) to fill the most important gap. Never ask more than one per reply.
{$questions}
RULES;
    }

    /**
     * Build the structured context snapshot passed to the LLM.
     *
     * @return array<string, mixed>
     */
    private function buildContext(): array
    {
        $recentSessions = $this->user
            ->workoutSessions()
            ->with('exerciseEntries')
            ->lastNDays(7)
            ->get()
            ->map(fn (WorkoutSession $s): array => [
                'date' => $s->logged_at->toDateString(),
                'duration_minutes' => $s->duration_minutes,
                'rpe' => $s->perceived_exertion,
                'energy' => $s->energy_level,
                'volume_kg' => $s->totalVolumeKg,
                'completed_planned' => $s->completed_planned,
                'notes' => $s->notes,
                'exercises' => $s->exerciseEntries->map(fn ($e): array => [
                    'name' => $e->exercise_name,
                    'sets' => $e->sets,
                    'reps' => $e->reps,
                    'weight' => $e->weight_kg,
                ])->toArray(),
            ]);

        $todayNutrition = $this->user
            ->nutritionLogs()
            ->today()
            ->get()
            ->map(fn (NutritionLog $log): array => [
                'meal_type' => $log->meal_type->value,
                'food_name' => $log->food_name,
                'calories' => $log->calories,
                'protein_g' => $log->protein_g,
                'carbs_g' => $log->carbs_g,
                'fat_g' => $log->fat_g,
            ]);

        return [
            'profile' => [
                'name' => $this->user->name,
                'experience_level' => $this->user->experience_level?->value,
                'primary_goal' => $this->user->primary_goal?->value,
                'goal_description' => $this->user->goal_description,
                'goal_deadline' => $this->user->goal_deadline?->toDateString(),
                'target_weight_kg' => $this->user->target_weight_kg,
                'current_weight_kg' => $this->user->current_weight_kg,
                'training_days_per_week' => $this->user->training_days_per_week,
            ],
            'stats' => [
                'weekly_adherence_rate' => $this->user->weekly_adherence_rate,
                'current_streak_days' => $this->user->current_streak_days,
                'last_active_at' => $this->user->last_active_at?->toDateString(),
            ],
            'personal_records' => $this->personalRecords,
            'recent_sessions' => $recentSessions,
            'today_nutrition' => $todayNutrition,
        ];
    }
}
