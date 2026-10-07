<?php

declare(strict_types=1);

namespace App\Interpretation\Lab;

use App\Ai\Agents\ClassifierAgent;
use App\Ai\SystemOne\SystemOneClient;
use App\Ai\SystemOneMessageClassifier;
use App\Contracts\Ai\MessageClassifier;
use App\Enums\MessageKind;
use App\Interpretation\RuleClassifier;
use Throwable;

/**
 * Runs one message through each classifier on its own, side by side, so a
 * model (rules, the LLM, Laya, Jev) can be judged alone. "pipeline" is what
 * the app really uses (config ai.classifier.driver). Saves nothing.
 */
final readonly class ClassifierLab
{
    public const array DRIVERS = ['rules', 'llm', 'laya', 'jev', 'pipeline'];

    public function __construct(
        private RuleClassifier $rules,
        private MessageClassifier $pipeline,
    ) {}

    /**
     * @param  list<string>  $drivers
     * @return list<LabResult>
     */
    public function run(string $text, array $drivers = self::DRIVERS): array
    {
        return array_map(fn (string $driver): LabResult => $this->one($driver, $text), $drivers);
    }

    /**
     * Scores every driver against a labelled file: one "kind | message" per line.
     *
     * @param  list<string>  $drivers
     * @return array{cases: list<array{text: string, expected: string, results: list<LabResult>}>, summary: array<string, array{correct: int, total: int, errors: int, avg_ms: float}>}
     */
    public function evaluate(string $path, array $drivers): array
    {
        $cases = [];
        $summary = array_fill_keys($drivers, ['correct' => 0, 'total' => 0, 'errors' => 0, 'avg_ms' => 0.0]);

        foreach (self::labelled($path) as [$expected, $text]) {
            $results = $this->run($text, $drivers);

            foreach ($results as $result) {
                $row = &$summary[$result->driver];
                $row['total']++;
                $row['avg_ms'] += $result->ms;
                $row['errors'] += $result->error !== null ? 1 : 0;
                $row['correct'] += $result->kind === $expected ? 1 : 0;
                unset($row);
            }

            $cases[] = ['text' => $text, 'expected' => $expected, 'results' => $results];
        }

        foreach ($summary as $driver => $row) {
            $summary[$driver]['avg_ms'] = $row['total'] > 0 ? round($row['avg_ms'] / $row['total'], 1) : 0.0;
        }

        return ['cases' => $cases, 'summary' => $summary];
    }

    /**
     * @return list<array{0: string, 1: string}> [expected kind, message]
     */
    public static function labelled(string $path): array
    {
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        $cases = [];

        foreach ($lines as $line) {
            if (str_starts_with(ltrim($line), '#') || ! str_contains($line, '|')) {
                continue;
            }

            [$kind, $text] = array_map('trim', explode('|', $line, 2));

            if (MessageKind::tryFrom($kind) !== null && $text !== '') {
                $cases[] = [$kind, $text];
            }
        }

        return $cases;
    }

    private function one(string $driver, string $text): LabResult
    {
        $started = hrtime(true);

        try {
            [$kind, $confidence, $probabilities] = match ($driver) {
                'rules' => $this->byRules($text),
                'llm' => $this->byLlm($text),
                'laya', 'jev' => $this->bySystemOne($driver, $text),
                'pipeline' => $this->byPipeline($text),
            };

            return new LabResult($driver, $kind, $confidence, $probabilities, self::ms($started));
        } catch (Throwable $e) {
            return new LabResult($driver, null, null, [], self::ms($started), $e->getMessage());
        }
    }

    /** @return array{0: ?string, 1: ?float, 2: array<string, float>} */
    private function byRules(string $text): array
    {
        $classification = $this->rules->classify($text);

        return [$classification?->kind->value, $classification?->confidence, []];
    }

    /** @return array{0: ?string, 1: ?float, 2: array<string, float>} */
    private function byLlm(string $text): array
    {
        $raw = (new ClassifierAgent)->prompt(
            $text,
            provider: config('ai.jobs.log.provider'),
            model: config('ai.jobs.log.model'),
        )->toArray();

        return [(string) ($raw['kind'] ?? ''), is_numeric($raw['confidence'] ?? null) ? (float) $raw['confidence'] : null, []];
    }

    /** @return array{0: ?string, 1: ?float, 2: array<string, float>} */
    private function bySystemOne(string $driver, string $text): array
    {
        $answer = SystemOneClient::fromConfig($driver)
            ->choice($text, SystemOneMessageClassifier::INSTRUCTIONS, MessageKind::criteria());

        return [$answer->choice, $answer->confidence, $answer->probabilities];
    }

    /** @return array{0: ?string, 1: ?float, 2: array<string, float>} */
    private function byPipeline(string $text): array
    {
        $classification = $this->pipeline->classify($text);

        return [$classification->kind->value, $classification->confidence, ['decided by '.$classification->decidedBy => 1.0]];
    }

    private static function ms(int $started): float
    {
        return round((hrtime(true) - $started) / 1e6, 1);
    }
}
