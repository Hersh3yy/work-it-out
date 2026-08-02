<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Contracts\Ai\SmartLogParser;
use App\Models\User;
use RuntimeException;

/**
 * In-memory SmartLogParser for tests. Returns a canned structured payload;
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
            'summary' => '3×5 Bench Press at 100kg',
            'duration_minutes' => 45,
            'perceived_exertion' => 8,
            'energy_level' => 4,
            'exercises' => [
                [
                    'exercise_name' => 'Bench Press',
                    'sets' => 3,
                    'reps' => 5,
                    'weight_kg' => 100.0,
                ],
            ],
            'lt_surge_feedback' => 'Solid pressing, Soldier.',
            'shen_feedback' => 'Strong session — great load management.',
            'latika_feedback' => 'Wonderful effort. Remember to stretch.',
            'diary_text' => 'Benched 100kg for 3 sets of 5.',
            'rpg_strength_delta' => 2,
            'rpg_stamina_delta' => 0,
            'rpg_vitality_delta' => 0,
            'rpg_stat_name' => 'Bench Press Peak',
            'rpg_stat_category' => 'strength',
            'rpg_stat_reason' => 'New heavy triple.',
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

        return $this->payload + ['log_type' => 'general', 'summary' => $message];
    }
}
