<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

/**
 * All-in-one natural-language log parser.
 *
 * One AI call that simultaneously:
 *   1. Classifies and parses the logged activity (workout, meal, biometrics)
 *   2. Generates a short reaction from each of the three coaches
 *   3. Writes a one-sentence diary entry
 *   4. Proposes integer RPG stat deltas and an optional custom stat update
 *
 * Keeping everything in a single prompt minimises token usage — the model
 * has full context to write coach reactions that actually reference the
 * specific exercises, foods, or metrics that were logged.
 */
final class SmartLogAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function __construct(private readonly User $user) {}

    public function instructions(): string
    {
        $today   = now()->toDateString();
        $profile = json_encode([
            'name'          => $this->user->name,
            'primary_goal'  => $this->user->primary_goal?->value,
            'experience'    => $this->user->experience_level?->value,
            'rpg_strength'  => $this->user->rpg_strength,
            'rpg_stamina'   => $this->user->rpg_stamina,
            'rpg_vitality'  => $this->user->rpg_vitality,
        ], JSON_PRETTY_PRINT);

        return <<<INSTRUCTIONS
You are a fitness data coordinator for the Feetness app. A user just logged an activity in free text. Today is {$today}.

User profile:
{$profile}

Your tasks:
1. **Parse** the log into structured data (workout, meal, biometrics, or general).
2. **Write a short reaction** from each of the three coaches (Lt. Surge, Shen, Latika). Each reaction must be 1-3 sentences, in character, directly referencing what was actually logged.
3. **Write one diary sentence** — a brief third-person narrative of the event, from a joint-coach perspective.
4. **Propose RPG stat deltas** (small integers, typically 0-3 per stat):
   - rpg_strength_delta: for resistance training, strength work
   - rpg_stamina_delta: for cardio, endurance, sport
   - rpg_vitality_delta: for clean meals, recovery, sleep logs
5. **Propose one custom RPG stat update** — a specific qualitative stat tied to what was logged (e.g. "Bench Press Peak", "5K Pace", "Clean Eating Score"). Set rpg_stat_name/category/reason if applicable.

Rules:
- Do NOT invent data the user did not provide.
- Exercise sets/reps/weight should come directly from the text.
- Calorie/macro values are estimates if not stated explicitly — mark them nullable.
- For workouts with multiple exercises, parse each exercise into the exercises array.
- RPG deltas should be small (0–3). Exceptional sessions may earn 4–5. Never more than 5 per stat.
- If the message is ambiguous or unrelated to fitness, log_type = "general" and skip workout/meal/biometrics fields.
INSTRUCTIONS;
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        $exerciseItem = $schema->object([
            'exercise_name'    => $schema->string()->required(),
            'sets'             => $schema->integer()->nullable(),
            'reps'             => $schema->integer()->nullable(),
            'weight_kg'        => $schema->number()->nullable(),
            'duration_seconds' => $schema->integer()->nullable(),
            'distance_meters'  => $schema->number()->nullable(),
            'notes'            => $schema->string()->nullable(),
        ]);

        return [
            // ── Classification ───────────────────────────────────────────────
            'log_type' => $schema->string()
                ->enum(['workout', 'meal', 'biometrics', 'general'])
                ->required(),

            'summary' => $schema->string()
                ->description('Short human-readable summary, e.g. "3×10 Bench Press + 20 min run"')
                ->required(),

            // ── Workout fields ────────────────────────────────────────────────
            'duration_minutes'   => $schema->integer()->nullable(),
            'perceived_exertion' => $schema->integer()->nullable()
                ->description('Rate of perceived exertion 1–10'),
            'energy_level'       => $schema->integer()->nullable()
                ->description('Self-reported energy level 1–5'),
            'workout_notes'      => $schema->string()->nullable(),
            'exercises'          => $schema->array()->items($exerciseItem),

            // ── Meal fields ───────────────────────────────────────────────────
            'meal_type'          => $schema->string()
                ->enum(['breakfast', 'lunch', 'dinner', 'snack', 'supplement'])
                ->nullable(),
            'food_name'          => $schema->string()->nullable(),
            'calories'           => $schema->integer()->nullable(),
            'protein_g'          => $schema->number()->nullable(),
            'carbs_g'            => $schema->number()->nullable(),
            'fat_g'              => $schema->number()->nullable(),

            // ── Biometrics fields ─────────────────────────────────────────────
            'weight_kg_stat'     => $schema->number()->nullable(),

            // ── Coach reactions ───────────────────────────────────────────────
            'lt_surge_feedback'  => $schema->string()->required(),
            'shen_feedback'      => $schema->string()->required(),
            'latika_feedback'    => $schema->string()->required(),
            'diary_text'         => $schema->string()->required(),

            // ── RPG deltas ────────────────────────────────────────────────────
            'rpg_strength_delta' => $schema->integer()->required(),
            'rpg_stamina_delta'  => $schema->integer()->required(),
            'rpg_vitality_delta' => $schema->integer()->required(),

            // ── Custom RPG stat ───────────────────────────────────────────────
            'rpg_stat_name'      => $schema->string()->nullable(),
            'rpg_stat_category'  => $schema->string()
                ->enum(['strength', 'stamina', 'vitality'])
                ->nullable(),
            'rpg_stat_reason'    => $schema->string()->nullable(),
        ];
    }
}
