<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Contracts\Ai\LimitationParser;
use RuntimeException;

/**
 * Limitation parser for tests: a fixed answer, "not a limitation" by default.
 */
final class FakeLimitationParser implements LimitationParser
{
    /** @var list<string> */
    public array $calls = [];

    private bool $shouldFail = false;

    /**
     * @param  array<string, mixed>  $facts
     */
    public function __construct(private array $facts = []) {}

    public static function backUnclear(): self
    {
        return new self([
            'is_limitation' => true,
            'body_area' => null,
            'candidates' => ['lower_back', 'upper_back'],
            'severity' => null,
            'duration_days' => 14,
            'started_days_ago' => 0,
            'note' => 'hurt back, go slow for 2 weeks',
        ]);
    }

    public static function knee(): self
    {
        return new self([
            'is_limitation' => true,
            'body_area' => 'knee',
            'candidates' => [],
            'severity' => 'mild',
            'duration_days' => null,
            'started_days_ago' => null,
            'note' => 'left knee hurts when squatting',
        ]);
    }

    public function failing(): self
    {
        $this->shouldFail = true;

        return $this;
    }

    public function parse(string $text): array
    {
        $this->calls[] = $text;

        if ($this->shouldFail) {
            throw new RuntimeException('Fake limitation parser outage');
        }

        return $this->facts + [
            'is_limitation' => false,
            'body_area' => null,
            'candidates' => [],
            'severity' => null,
            'duration_days' => null,
            'started_days_ago' => null,
            'note' => '',
        ];
    }
}
