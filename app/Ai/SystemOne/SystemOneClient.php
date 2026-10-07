<?php

declare(strict_types=1);

namespace App\Ai\SystemOne;

use App\Exceptions\AiUnavailable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * HTTP client for typed-decision ("System One") models: Laya served by
 * laya-serve, or TypeSafe's hosted Jev. Both take a state plus typed questions
 * and answer with a choice and a probability per option, in one forward pass.
 * No text is generated, so there is nothing to parse.
 */
final readonly class SystemOneClient
{
    public function __construct(
        public string $name,
        private string $baseUrl,
        private string $path,
        private ?string $apiKey = null,
        private ?string $model = null,
        private int $timeout = 10,
    ) {}

    public static function fromConfig(string $name): self
    {
        $config = (array) config("ai.classifier.{$name}");

        return new self(
            name: $name,
            baseUrl: rtrim((string) ($config['url'] ?? ''), '/'),
            path: (string) ($config['path'] ?? '/v1/systemone'),
            apiKey: filled($config['key'] ?? null) ? (string) $config['key'] : null,
            model: filled($config['model'] ?? null) ? (string) $config['model'] : null,
        );
    }

    /**
     * @param  array<string, string>  $criteria  option => what it means
     *
     * @throws AiUnavailable
     */
    public function choice(string $state, string $instructions, array $criteria): ChoiceAnswer
    {
        $payload = array_filter([
            'model' => $this->model,
            'state' => ['message' => $state],
            'questions' => [
                'kind' => [
                    'type' => 'choice',
                    'instructions' => $instructions,
                    'criteria' => $criteria,
                ],
            ],
        ], static fn (mixed $value): bool => $value !== null);

        try {
            $response = Http::baseUrl($this->baseUrl)
                ->timeout($this->timeout)
                ->acceptJson()
                ->asJson()
                ->when($this->apiKey !== null, fn ($http) => $http->withToken((string) $this->apiKey))
                ->post($this->path, $payload);
        } catch (ConnectionException) {
            throw AiUnavailable::because("systemone:{$this->name}", "cannot reach {$this->baseUrl}");
        }

        if ($response->failed()) {
            throw AiUnavailable::because("systemone:{$this->name}", 'HTTP '.$response->status());
        }

        // laya-serve answers under "answers"; TypeSafe's SDK also exposes "choices".
        $answer = $response->json('answers.kind') ?? $response->json('choices.kind');
        $choice = is_array($answer) ? ($answer['choice'] ?? null) : null;

        if (! is_string($choice) || ! array_key_exists($choice, $criteria)) {
            throw AiUnavailable::because("systemone:{$this->name}", 'answer missing a valid choice');
        }

        $probabilities = array_map('floatval', array_intersect_key((array) ($answer['probabilities'] ?? []), $criteria));
        arsort($probabilities);

        // laya-serve's "confidence" is a separate act/abstain signal, not the
        // pick's probability; "answer_confidence" is. Prefer it, then the
        // pick's own probability, then whatever "confidence" says (Jev).
        $confidence = match (true) {
            is_numeric($answer['answer_confidence'] ?? null) => (float) $answer['answer_confidence'],
            isset($probabilities[$choice]) => $probabilities[$choice],
            is_numeric($answer['confidence'] ?? null) => (float) $answer['confidence'],
            default => 0.0,
        };

        return new ChoiceAnswer($choice, max(0.0, min(1.0, $confidence)), $probabilities);
    }
}
