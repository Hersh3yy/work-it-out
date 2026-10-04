<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Contracts\Ai\SmartLogParser;
use App\Models\User;
use RuntimeException;

/**
 * In-memory SmartLogParser for tests. Returns a canned facts-only payload;
 * use the named constructors for common log types.
 */
final class FakeSmartLogParser implements SmartLogParser
{
    /** @var list<array{user_id: int, message: string}> */
    public array $calls = [];

    private bool $shouldFail = false;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        private readonly array $payload = [],
    ) {}

    public static function workout(): self
    {
        return new self([
            'log_type' => 'workout',
            'summary' => 'Bench Press 3x5 @ 100 kg',
            'logged_on' => null,
            'duration_minutes' => 45,
            'perceived_exertion' => 8,
            'energy_level' => 4,
            'workout_notes' => null,
            'exercises' => [
                [
                    'exercise_name' => 'Bench Press',
                    'sets' => 3,
                    'reps' => 5,
                    'weight_kg' => 100.0,
                    'duration_seconds' => null,
                    'distance_meters' => null,
                    'notes' => null,
                ],
            ],
            'meal_type' => null,
            'food_name' => null,
            'weight_kg_stat' => null,
            'questions' => [],
        ]);
    }

    /**
     * An incline press with no weight given: stored without one, one question asked.
     */
    public static function missingWeight(): self
    {
        return new self([
            'log_type' => 'workout',
            'summary' => 'Incline DB Press 3x10',
            'exercises' => [
                ['exercise_name' => 'Incline DB Press', 'sets' => 3, 'reps' => 10, 'weight_kg' => null],
            ],
            'questions' => [
                ['field' => 'exercises.0.weight_kg', 'question' => 'What weight for the incline press?'],
            ],
        ]);
    }

    public function failing(): self
    {
        $this->shouldFail = true;

        return $this;
    }

    public function parse(User $user, string $message): array
    {
        $this->calls[] = ['user_id' => $user->id, 'message' => $message];

        if ($this->shouldFail) {
            throw new RuntimeException('Fake AI outage');
        }

        return $this->payload + ['log_type' => 'general', 'summary' => $message, 'exercises' => [], 'questions' => []];
    }
}
