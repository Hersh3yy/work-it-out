<?php

declare(strict_types=1);

namespace App\Interpretation;

use App\Enums\MessageKind;

/**
 * What the interpreter made of one message: the kind, the facts to record,
 * or a reply that ends the turn with nothing saved.
 */
final readonly class Interpretation
{
    /**
     * @param  array<string, mixed>|null  $parsed
     */
    /**
     * @param  array<string, mixed>|null  $limitation  facts from the LimitationParser when the message states one
     */
    public function __construct(
        public Classification $classification,
        public ?array $parsed = null,
        public ?string $reply = null,
        public ?array $limitation = null,
    ) {}

    public function shouldRecord(): bool
    {
        return $this->reply === null && $this->parsed !== null;
    }

    /** A question for the coach. Doors that can answer it do; the rest show the reply. */
    public function isCoachQuestion(): bool
    {
        return $this->classification->kind === MessageKind::CoachQuestion;
    }

    public function isNotUnderstood(): bool
    {
        return $this->classification->kind === MessageKind::Unknown;
    }

    /** The message is about a limitation and nothing else. */
    public function isLimitation(): bool
    {
        return $this->reply === null && $this->limitation !== null && $this->parsed === null;
    }

    /** A log that also says how a limitation is going. */
    public function alsoAboutLimitation(): bool
    {
        return $this->shouldRecord() && $this->limitation !== null;
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
            'limitation' => $this->limitation,
            'would_record' => $this->shouldRecord() || $this->isLimitation(),
        ];
    }
}
