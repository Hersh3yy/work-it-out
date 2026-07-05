<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Ai\Agents\SmartLogAgent;
use App\Http\Controllers\Controller;
use App\Jobs\UpdateUserStats;
use App\Models\ActivityFeedback;
use App\Models\CustomRpgStat;
use App\Models\DiaryEntry;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Processes a single natural-language log message.
 *
 * One AI call parses the input, generates three-coach reactions, a diary entry,
 * and RPG deltas. The controller persists everything into the appropriate tables
 * and returns a rich response for the Flutter client to render immediately.
 *
 * Privacy note: raw_message is stored only to give the AI diary/feedback context.
 * No plain-text content is sent outside the request boundary beyond the AI call.
 */
final class SmartLogController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'message' => ['required', 'string', 'min:3', 'max:2000'],
        ]);

        /** @var User $user */
        $user    = $request->user();
        $message = $request->string('message')->value();

        try {
            $agent    = new SmartLogAgent($user);
            $response = $agent->forUser($user)->prompt($message);

            /** @var array<string, mixed> $parsed */
            $parsed = $response->structured();
        } catch (Throwable $e) {
            return response()->json(
                ['message' => 'The AI log processor is temporarily unavailable. Please try again.'],
                Response::HTTP_SERVICE_UNAVAILABLE
            );
        }

        $loggable     = null;
        $loggableType = null;
        $loggableId   = null;

        // ── Persist the parsed structured data ───────────────────────────────
        switch ($parsed['log_type'] ?? 'general') {
            case 'workout':
                $loggable = $this->persistWorkout($user, $parsed);
                if ($loggable) {
                    $loggableType = $loggable->getMorphClass();
                    $loggableId   = $loggable->getKey();
                    UpdateUserStats::dispatch($user);
                }
                break;

            case 'meal':
                $loggable = $this->persistMeal($user, $parsed);
                if ($loggable) {
                    $loggableType = $loggable->getMorphClass();
                    $loggableId   = $loggable->getKey();
                }
                break;

            case 'biometrics':
                $loggable = $this->persistBiometrics($user, $parsed);
                if ($loggable) {
                    $loggableType = $loggable->getMorphClass();
                    $loggableId   = $loggable->getKey();
                }
                break;
        }

        // ── Coach feedback ────────────────────────────────────────────────────
        $feedback = ActivityFeedback::create([
            'user_id'      => $user->id,
            'loggable_type'=> $loggableType,
            'loggable_id'  => $loggableId,
            'raw_message'  => $message,
            'log_summary'  => $parsed['summary'] ?? $message,
            'lt_surge'     => $parsed['lt_surge_feedback'] ?? null,
            'shen'         => $parsed['shen_feedback'] ?? null,
            'latika'       => $parsed['latika_feedback'] ?? null,
        ]);

        // ── Diary entry ───────────────────────────────────────────────────────
        $diary = DiaryEntry::create([
            'user_id'              => $user->id,
            'activity_feedback_id' => $feedback->id,
            'content'              => $parsed['diary_text'] ?? $parsed['summary'],
        ]);

        // ── RPG stat updates ──────────────────────────────────────────────────
        $rpgSnapshot = $this->applyRpgDeltas($user, $parsed);
        $this->applyCustomRpgStat($user, $parsed);

        return response()->json([
            'log_type'    => $parsed['log_type'],
            'summary'     => $parsed['summary'],
            'feedback'    => [
                'id'       => $feedback->id,
                'lt_surge' => $feedback->lt_surge,
                'shen'     => $feedback->shen,
                'latika'   => $feedback->latika,
            ],
            'diary'       => [
                'id'      => $diary->id,
                'content' => $diary->content,
            ],
            'rpg'         => $rpgSnapshot,
            'loggable_id' => $loggableId,
        ], Response::HTTP_CREATED);
    }

    // ── Private persistence helpers ───────────────────────────────────────────

    private function persistWorkout(User $user, array $parsed): ?\App\Models\WorkoutSession
    {
        $session = $user->workoutSessions()->create([
            'logged_at'         => now(),
            'duration_minutes'  => $parsed['duration_minutes'] ?? null,
            'perceived_exertion'=> $parsed['perceived_exertion'] ?? null,
            'energy_level'      => $parsed['energy_level'] ?? null,
            'notes'             => $parsed['workout_notes'] ?? null,
            'completed_planned' => false,
        ]);

        $exercises = $parsed['exercises'] ?? [];
        foreach ($exercises as $index => $ex) {
            $session->exerciseEntries()->create([
                'exercise_name'    => $ex['exercise_name'] ?? 'Unknown',
                'sets'             => $ex['sets'] ?? null,
                'reps'             => $ex['reps'] ?? null,
                'weight_kg'        => $ex['weight_kg'] ?? null,
                'duration_seconds' => $ex['duration_seconds'] ?? null,
                'distance_meters'  => $ex['distance_meters'] ?? null,
                'notes'            => $ex['notes'] ?? null,
                'sort_order'       => $index,
            ]);
        }

        return $session;
    }

    private function persistMeal(User $user, array $parsed): ?\App\Models\NutritionLog
    {
        if (empty($parsed['food_name'])) {
            return null;
        }

        return $user->nutritionLogs()->create([
            'logged_at' => now(),
            'meal_type' => $parsed['meal_type'] ?? 'snack',
            'food_name' => $parsed['food_name'],
            'calories'  => $parsed['calories'] ?? null,
            'protein_g' => $parsed['protein_g'] ?? null,
            'carbs_g'   => $parsed['carbs_g'] ?? null,
            'fat_g'     => $parsed['fat_g'] ?? null,
        ]);
    }

    private function persistBiometrics(User $user, array $parsed): ?\App\Models\BodyWeightLog
    {
        $weightKg = $parsed['weight_kg_stat'] ?? null;

        if ($weightKg === null) {
            return null;
        }

        $log = $user->bodyWeightLogs()->create([
            'logged_at' => today(),
            'weight_kg' => $weightKg,
        ]);

        $user->update(['current_weight_kg' => $weightKg]);

        return $log;
    }

    /**
     * Apply clamped RPG deltas and return the updated snapshot.
     *
     * @param  array<string, mixed>  $parsed
     * @return array{strength: int, stamina: int, vitality: int}
     */
    private function applyRpgDeltas(User $user, array $parsed): array
    {
        $clamp = static fn (int $current, int $delta): int => min(100, max(1, $current + max(0, min(5, $delta))));

        $strength = $clamp($user->rpg_strength, (int) ($parsed['rpg_strength_delta'] ?? 0));
        $stamina  = $clamp($user->rpg_stamina, (int) ($parsed['rpg_stamina_delta'] ?? 0));
        $vitality = $clamp($user->rpg_vitality, (int) ($parsed['rpg_vitality_delta'] ?? 0));

        $user->update([
            'rpg_strength' => $strength,
            'rpg_stamina'  => $stamina,
            'rpg_vitality' => $vitality,
        ]);

        return ['strength' => $strength, 'stamina' => $stamina, 'vitality' => $vitality];
    }

    /**
     * Create or increment a custom RPG stat if the AI proposed one.
     *
     * @param  array<string, mixed>  $parsed
     */
    private function applyCustomRpgStat(User $user, array $parsed): void
    {
        $statName = $parsed['rpg_stat_name'] ?? null;

        if (empty($statName)) {
            return;
        }

        $existing = $user->customRpgStats()
            ->whereRaw('LOWER(name) = ?', [strtolower($statName)])
            ->first();

        if ($existing) {
            $existing->update([
                'value'           => min(100, $existing->value + 5),
                'level'           => $existing->level + 1,
                'change_reason'   => $parsed['rpg_stat_reason'] ?? null,
                'last_updated_at' => now(),
            ]);
        } else {
            // Soft-cap: keep only 10 custom stats, drop the least recently updated
            $count = $user->customRpgStats()->count();
            if ($count >= 10) {
                $user->customRpgStats()->oldest('last_updated_at')->first()?->delete();
            }

            CustomRpgStat::create([
                'user_id'         => $user->id,
                'name'            => $statName,
                'value'           => 50,
                'level'           => 1,
                'unit'            => 'score',
                'category'        => $parsed['rpg_stat_category'] ?? 'strength',
                'change_reason'   => $parsed['rpg_stat_reason'] ?? null,
                'last_updated_at' => now(),
            ]);
        }
    }
}
