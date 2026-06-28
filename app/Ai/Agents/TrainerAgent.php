<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Models\NutritionLog;
use App\Models\User;
use App\Models\WorkoutSession;
use Illuminate\Support\Stringable;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Promptable;

/**
 * The AI fitness trainer agent.
 *
 * Persona (General / Coach) is injected via the User's trainer_persona enum,
 * which provides its own systemPrompt() — following the Strategy pattern so
 * the agent never switches on persona type directly.
 *
 * Conversation history is persisted by the AI SDK's RemembersConversations
 * trait; no custom ai_conversations table is needed.
 *
 * @see https://refactoring.guru/design-patterns/strategy
 */
final class TrainerAgent implements Agent, Conversational
{
    use Promptable, RemembersConversations;

    public function __construct(private readonly User $user) {}

    public function instructions(): Stringable|string
    {
        $personaPrompt = $this->user->trainer_persona->systemPrompt();
        $contextJson   = json_encode($this->buildContext(), JSON_PRETTY_PRINT);
        $today         = now()->toDateString();

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
- If the user asks something unrelated to fitness, redirect them back to training. The General does this bluntly. The Coach does it warmly.
- Never diagnose injuries. If the user mentions pain, recommend they see a professional.
- Today's date is {$today}.
INSTRUCTIONS;
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
                'date'              => $s->logged_at->toDateString(),
                'duration_minutes'  => $s->duration_minutes,
                'rpe'               => $s->perceived_exertion,
                'energy'            => $s->energy_level,
                'volume_kg'         => $s->totalVolumeKg,
                'completed_planned' => $s->completed_planned,
                'notes'             => $s->notes,
                'exercises'         => $s->exerciseEntries->map(fn ($e): array => [
                    'name'   => $e->exercise_name,
                    'sets'   => $e->sets,
                    'reps'   => $e->reps,
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
                'calories'  => $log->calories,
                'protein_g' => $log->protein_g,
                'carbs_g'   => $log->carbs_g,
                'fat_g'     => $log->fat_g,
            ]);

        return [
            'profile' => [
                'name'                   => $this->user->name,
                'experience_level'       => $this->user->experience_level?->value,
                'primary_goal'           => $this->user->primary_goal?->value,
                'goal_description'       => $this->user->goal_description,
                'goal_deadline'          => $this->user->goal_deadline?->toDateString(),
                'target_weight_kg'       => $this->user->target_weight_kg,
                'current_weight_kg'      => $this->user->current_weight_kg,
                'training_days_per_week' => $this->user->training_days_per_week,
            ],
            'stats' => [
                'weekly_adherence_rate' => $this->user->weekly_adherence_rate,
                'current_streak_days'   => $this->user->current_streak_days,
                'last_active_at'        => $this->user->last_active_at?->toDateString(),
            ],
            'recent_sessions'  => $recentSessions,
            'today_nutrition'  => $todayNutrition,
        ];
    }
}
