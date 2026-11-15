<?php

declare(strict_types=1);

namespace App\Ai;

use App\Ai\Agents\SmartLogAgent;
use App\Contracts\Ai\SmartLogParser;
use App\Enums\LogType;
use App\Exceptions\AiUnavailable;
use App\Models\User;

/**
 * Laravel AI SDK adapter for the SmartLogParser port.
 *
 * SmartLogAgent is not Conversational, so there is no forUser(); the
 * structured response is read with toArray(). The payload is rebuilt here, at
 * the boundary, from known keys only: types coerced, strings capped, anything
 * else the model sent (coach lines, stat deltas) dropped. Domain limits
 * (rep ranges, how far back a date may go) are RecordSmartLog's job.
 */
final readonly class SdkSmartLogParser implements SmartLogParser
{
    public function parse(User $user, string $message): array
    {
        $agent = new SmartLogAgent(now()->toDateString());
        $ask = fn (): array => ModelChain::ask(AiJob::Log, fn (?string $provider, ?string $model): array => $agent->prompt(
            $message,
            provider: $provider,
            model: $model,
        )->toArray());

        try {
            return $this->normalize($ask());
        } catch (AiUnavailable) {
            // One retry when the answer did not fit the form; a second miss is an outage.
            return $this->normalize($ask());
        }
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     *
     * @throws AiUnavailable
     */
    private function normalize(array $raw): array
    {
        $logType = $raw['log_type'] ?? null;
        $summary = $raw['summary'] ?? null;

        if (! is_string($logType) || LogType::tryFrom($logType) === null) {
            throw AiUnavailable::because(SmartLogAgent::class, 'payload missing a valid log_type');
        }
        if (! is_string($summary) || trim($summary) === '') {
            throw AiUnavailable::because(SmartLogAgent::class, 'payload missing summary');
        }

        $loggedOn = $raw['logged_on'] ?? null;

        return [
            'log_type' => $logType,
            'summary' => mb_substr(trim($summary), 0, 255),
            'logged_on' => is_string($loggedOn) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $loggedOn) ? $loggedOn : null,
            'duration_minutes' => self::int($raw['duration_minutes'] ?? null),
            'perceived_exertion' => self::int($raw['perceived_exertion'] ?? null),
            'energy_level' => self::int($raw['energy_level'] ?? null),
            'workout_notes' => self::text($raw['workout_notes'] ?? null, 1000),
            'exercises' => array_values(array_filter(array_map(
                self::exercise(...),
                is_array($raw['exercises'] ?? null) ? $raw['exercises'] : [],
            ))),
            'weight_kg_stat' => self::number($raw['weight_kg_stat'] ?? null),
            'questions' => array_slice(array_values(array_filter(array_map(
                self::question(...),
                is_array($raw['questions'] ?? null) ? $raw['questions'] : [],
            ))), 0, 1),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function exercise(mixed $item): ?array
    {
        $name = is_array($item) ? self::text($item['exercise_name'] ?? null, 100) : null;

        if ($name === null) {
            return null;
        }

        return self::setValues($item) + [
            'exercise_name' => $name,
            'sets' => self::int($item['sets'] ?? null),
            'weight_lb' => self::number($item['weight_lb'] ?? null),
            'per_side_kg' => self::number($item['per_side_kg'] ?? null),
            'distance_miles' => self::number($item['distance_miles'] ?? null),
            'pace_seconds_per_km' => self::int($item['pace_seconds_per_km'] ?? null),
            'bodyweight' => (bool) ($item['bodyweight'] ?? false),
            'assisted' => (bool) ($item['assisted'] ?? false),
            'group' => self::text($item['group'] ?? null, 40),
            'notes' => self::text($item['notes'] ?? null, 255),
            'set_list' => array_slice(array_values(array_map(
                static fn (mixed $set): array => self::setValues(is_array($set) ? $set : []),
                is_array($item['set_list'] ?? null) ? $item['set_list'] : [],
            )), 0, 50),
        ];
    }

    /**
     * @param  array<string, mixed>  $set
     * @return array{reps: int|null, weight_kg: float|null, duration_seconds: int|null, distance_meters: float|null, rpe: float|null, rir: int|null}
     */
    private static function setValues(array $set): array
    {
        return [
            'reps' => self::int($set['reps'] ?? null),
            'weight_kg' => self::number($set['weight_kg'] ?? null),
            'duration_seconds' => self::int($set['duration_seconds'] ?? null),
            'distance_meters' => self::number($set['distance_meters'] ?? null),
            'rpe' => self::number($set['rpe'] ?? null),
            'rir' => self::int($set['rir'] ?? null),
        ];
    }

    /**
     * @return array{field: string, question: string}|null
     */
    private static function question(mixed $item): ?array
    {
        $field = is_array($item) ? self::text($item['field'] ?? null, 60) : null;
        $question = is_array($item) ? self::text($item['question'] ?? null, 200) : null;

        return $field !== null && $question !== null
            ? ['field' => $field, 'question' => $question]
            : null;
    }

    private static function text(mixed $value, int $max): ?string
    {
        return is_string($value) && trim($value) !== '' ? mb_substr(trim($value), 0, $max) : null;
    }

    private static function int(mixed $value): ?int
    {
        return is_numeric($value) ? (int) round((float) $value) : null;
    }

    private static function number(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }
}
