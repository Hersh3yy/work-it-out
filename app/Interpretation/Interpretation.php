<?php

declare(strict_types=1);

namespace App\Interpretation;

/**
 * What the interpreter made of one message: the kind, the facts to record,
 * or a reply that ends the turn with nothing saved.
 */
final readonly class Interpretation
{
    /**
     * @param  array<string, mixed>|null  $parsed
     */
    public function __construct(
        public Classification $classification,
        public ?array $parsed = null,
        public ?string $reply = null,
    ) {}

    public function shouldRecord(): bool
    {
        return $this->reply === null && $this->parsed !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'classification' => $this->classification->toArray(),
            'parsed' => $this->parsed,
            'reply' => $this->reply,
            'would_record' => $this->shouldRecord(),
        ];
    }
}
