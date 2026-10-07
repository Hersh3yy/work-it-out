<?php

declare(strict_types=1);

namespace App\Ai;

use App\Ai\SystemOne\SystemOneClient;
use App\Enums\Intent;
use App\Exceptions\AiUnavailable;

/**
 * Asks a typed-decision model (Laya on this laptop, or Jev hosted) one
 * question: which intent is this message? The model picks one option and
 * gives a probability for every option. Nothing is saved.
 */
final readonly class IntentClassifier
{
    public const string QUESTION = 'What does the user mean with this message to their training app?';

    /**
     * @return array{intent: string, confidence: float, probabilities: array<string, float>, ms: float, driver: string}
     *
     * @throws AiUnavailable
     */
    public function classify(string $text, string $driver = 'laya'): array
    {
        $started = hrtime(true);

        $answer = SystemOneClient::fromConfig($driver)->choice($text, self::QUESTION, Intent::criteria());

        return [
            'intent' => $answer->choice,
            'confidence' => round($answer->confidence, 3),
            'probabilities' => array_map(static fn (float $p): float => round($p, 3), $answer->probabilities),
            'ms' => round((hrtime(true) - $started) / 1e6, 1),
            'driver' => $driver,
        ];
    }

    /**
     * Scores a labelled file, one "intent | message" per line.
     *
     * @return array{correct: int, total: int, avg_ms: float, cases: list<array{text: string, expected: string, got: ?string, confidence: ?float, error: ?string}>}
     */
    public function evaluate(string $path, string $driver = 'laya'): array
    {
        $cases = [];
        $correct = 0;
        $ms = 0.0;

        foreach (self::labelled($path) as [$expected, $text]) {
            try {
                $result = $this->classify($text, $driver);
                $ms += $result['ms'];
                $correct += $result['intent'] === $expected ? 1 : 0;
                $cases[] = ['text' => $text, 'expected' => $expected, 'got' => $result['intent'], 'confidence' => $result['confidence'], 'error' => null];
            } catch (AiUnavailable $e) {
                $cases[] = ['text' => $text, 'expected' => $expected, 'got' => null, 'confidence' => null, 'error' => $e->getMessage()];
            }
        }

        return [
            'correct' => $correct,
            'total' => count($cases),
            'avg_ms' => $cases === [] ? 0.0 : round($ms / count($cases), 1),
            'cases' => $cases,
        ];
    }

    /**
     * @return list<array{0: string, 1: string}> [expected intent, message]
     */
    public static function labelled(string $path): array
    {
        $cases = [];

        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            if (str_starts_with(ltrim($line), '#') || ! str_contains($line, '|')) {
                continue;
            }

            [$intent, $text] = array_map('trim', explode('|', $line, 2));

            if (Intent::tryFrom($intent) !== null && $text !== '') {
                $cases[] = [$intent, $text];
            }
        }

        return $cases;
    }
}
