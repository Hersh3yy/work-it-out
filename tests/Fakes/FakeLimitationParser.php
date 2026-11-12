<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Contracts\Ai\LimitationParser;

/**
 * Limitation parser for tests: a fixed answer, "not a limitation" by default.
 */
final class FakeLimitationParser implements LimitationParser
{
    use FailsOnRequest;

    /** @var list<string> */
    public array $calls = [];

    /** @var list<array<string, mixed>> what the reader was told, one per call */
    public array $contexts = [];

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

    public function parse(string $text, array $context = []): array
    {
        $this->calls[] = $text;
        $this->contexts[] = $context;

        $this->failIfAsked();

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
