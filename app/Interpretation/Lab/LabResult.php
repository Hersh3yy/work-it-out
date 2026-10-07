<?php

declare(strict_types=1);

namespace App\Interpretation\Lab;

/**
 * One classifier's answer to one message in the lab. kind is null when the
 * driver had no opinion (rules) or failed (error says why).
 */
final readonly class LabResult
{
    /**
     * @param  array<string, float>  $probabilities
     */
    public function __construct(
        public string $driver,
        public ?string $kind,
        public ?float $confidence,
        public array $probabilities,
        public float $ms,
        public ?string $error = null,
    ) {}

    /**
     * @return array{driver: string, kind: ?string, confidence: ?float, probabilities: array<string, float>, ms: float, error: ?string}
     */
    public function toArray(): array
    {
        return [
            'driver' => $this->driver,
            'kind' => $this->kind,
            'confidence' => $this->confidence === null ? null : round($this->confidence, 3),
            'probabilities' => array_map(static fn (float $p): float => round($p, 3), $this->probabilities),
            'ms' => $this->ms,
            'error' => $this->error === null ? null : mb_strimwidth(strtok($this->error, "\n") ?: $this->error, 0, 200, '...'),
        ];
    }
}
