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
    public const int EXPLAIN_MAX_WORDS = 30;

    public const string QUESTION = 'What does the user mean with this message to their training app?';

    /**
     * @param  array<string, string>|null  $criteria  other option wording to try (lab), default Intent::criteria()
     * @return array{intent: string, confidence: float, probabilities: array<string, float>, ms: float, driver: string}
     *
     * @throws AiUnavailable
     */
    public function classify(string $text, string $driver = 'laya', ?array $criteria = null): array
    {
        $started = hrtime(true);

        $answer = SystemOneClient::fromConfig($driver)->choice($text, self::QUESTION, $criteria ?? Intent::criteria());

        return [
            'intent' => $answer->choice,
            'confidence' => round($answer->confidence, 3),
            'probabilities' => array_map(static fn (float $p): float => round($p, 3), $answer->probabilities),
            'ms' => round((hrtime(true) - $started) / 1e6, 1),
            'driver' => $driver,
        ];
    }

    /**
     * Why this answer: ask again with each word left out and see how much the
     * winning option's probability drops. A big drop means that word pushed
     * the model towards its answer; a negative number means it pulled away.
     *
     * @param  array<string, string>|null  $criteria
     * @return array{base: array<string, mixed>, words: list<array{index: int, word: string, without: string, probability_without: float, influence: float}>}
     *
     * @throws AiUnavailable
     */
    public function explain(string $text, string $driver = 'laya', ?array $criteria = null): array
    {
        $base = $this->classify($text, $driver, $criteria);
        $words = array_values(array_filter(preg_split('/\s+/', trim($text)) ?: [], static fn (string $w): bool => $w !== ''));
        $client = SystemOneClient::fromConfig($driver);
        $winner = $base['probabilities'][$base['intent']] ?? $base['confidence'];
        $influence = [];

        foreach (array_slice($words, 0, self::EXPLAIN_MAX_WORDS) as $index => $word) {
            $rest = $words;
            unset($rest[$index]);
            $without = implode(' ', $rest);

            $answer = $client->choice($without === '' ? '.' : $without, self::QUESTION, $criteria ?? Intent::criteria());
            $p = $answer->probabilities[$base['intent']] ?? 0.0;

            $influence[] = [
                'index' => $index,
                'word' => $word,
                'without' => $answer->choice,
                'probability_without' => round($p, 3),
                'influence' => round($winner - $p, 3),
            ];
        }

        return ['base' => $base, 'words' => $influence];
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
