<?php

declare(strict_types=1);

namespace App\Ai;

use App\Ai\Agents\ClassifierAgent;
use App\Contracts\Ai\MessageClassifier;
use App\Enums\MessageKind;
use App\Exceptions\AiUnavailable;
use App\Interpretation\Classification;
use App\Interpretation\RuleClassifier;

/**
 * Classify step adapter: plain rules first, the language model for the rest.
 * Runs on the log job's provider and model (`config/ai.php` `jobs.log`).
 *
 * @see https://refactoring.guru/design-patterns/chain-of-responsibility
 */
final readonly class SdkMessageClassifier implements MessageClassifier
{
    public function __construct(
        private RuleClassifier $rules,
    ) {}

    public function classify(string $text): Classification
    {
        $byRules = $this->rules->classify($text);

        if ($byRules !== null) {
            return $byRules;
        }

        $raw = ModelChain::ask('log', fn (?string $provider, ?string $model): array => (new ClassifierAgent)->prompt(
            $text,
            provider: $provider,
            model: $model,
        )->toArray());

        $kind = MessageKind::tryFrom((string) ($raw['kind'] ?? ''));

        if ($kind === null || $kind === MessageKind::Command) {
            throw AiUnavailable::because(ClassifierAgent::class, 'payload missing a valid kind');
        }

        $confidence = is_numeric($raw['confidence'] ?? null) ? (float) $raw['confidence'] : 0.0;

        return new Classification($kind, max(0.0, min(1.0, $confidence)), 'model');
    }
}
