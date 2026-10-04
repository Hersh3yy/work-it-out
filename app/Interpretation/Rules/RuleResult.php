<?php

declare(strict_types=1);

namespace App\Interpretation\Rules;

/**
 * A rule's verdict: the (possibly changed) payload, or a reply that stops
 * the log so nothing is saved.
 */
final readonly class RuleResult
{
    /**
     * @param  array<string, mixed>  $parsed
     */
    private function __construct(
        public array $parsed,
        public ?string $stopReply,
    ) {}

    /**
     * @param  array<string, mixed>  $parsed
     */
    public static function continue(array $parsed): self
    {
        return new self($parsed, null);
    }

    /**
     * @param  array<string, mixed>  $parsed
     */
    public static function stop(array $parsed, string $reply): self
    {
        return new self($parsed, $reply);
    }
}
