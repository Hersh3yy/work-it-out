<?php

declare(strict_types=1);

namespace App\Ai;

use App\Ai\SystemOne\SystemOneClient;
use App\Contracts\Ai\MessageClassifier;
use App\Enums\MessageKind;
use App\Interpretation\Classification;
use App\Interpretation\RuleClassifier;

/**
 * Classify step on a typed-decision model (Laya or Jev): plain rules first,
 * then one choice question over the message kinds. When the model is not
 * sure enough, the fallback classifier (the LLM) decides instead.
 *
 * @see https://refactoring.guru/design-patterns/chain-of-responsibility
 */
final readonly class SystemOneMessageClassifier implements MessageClassifier
{
    public const string INSTRUCTIONS = 'What kind of message did the user send to their training log?';

    public function __construct(
        private RuleClassifier $rules,
        private SystemOneClient $client,
        private float $minConfidence = 0.6,
        private ?MessageClassifier $fallback = null,
    ) {}

    public function classify(string $text): Classification
    {
        $byRules = $this->rules->classify($text);

        if ($byRules !== null) {
            return $byRules;
        }

        $answer = $this->client->choice($text, self::INSTRUCTIONS, MessageKind::criteria());

        if ($answer->confidence < $this->minConfidence && $this->fallback !== null) {
            return $this->fallback->classify($text);
        }

        return new Classification(MessageKind::from($answer->choice), $answer->confidence, $this->client->name);
    }
}
