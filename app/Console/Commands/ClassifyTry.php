<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Interpretation\Lab\ClassifierLab;
use App\Interpretation\Lab\LabResult;
use Illuminate\Console\Command;

/**
 * The classify step on its own, per model, side by side. Saves nothing.
 *
 *   php artisan classify:try "squat 90 90 95"
 *   php artisan classify:try "squat 90 90 95" --driver=laya --driver=llm
 *   php artisan classify:try --file=tests/Evals/classify.txt --driver=rules --driver=laya
 */
final class ClassifyTry extends Command
{
    protected $signature = 'classify:try
        {text? : One message, as you would text it}
        {--driver=* : rules, llm, laya, jev, pipeline (default: all)}
        {--file= : Labelled file, one "kind | message" per line, to score each driver}';

    protected $description = 'Try the classify step alone, per model, and score it against labelled messages';

    public function handle(ClassifierLab $lab): int
    {
        $drivers = array_values(array_intersect($this->option('driver') ?: ClassifierLab::DRIVERS, ClassifierLab::DRIVERS));

        if ($drivers === []) {
            $this->error('Unknown driver. Use: '.implode(', ', ClassifierLab::DRIVERS));

            return self::FAILURE;
        }

        if ($file = $this->option('file')) {
            return $this->evaluate($lab, (string) $file, $drivers);
        }

        $text = (string) $this->argument('text');

        if ($text === '') {
            $this->error('Give a message, or --file=tests/Evals/classify.txt');

            return self::FAILURE;
        }

        $this->table(
            ['driver', 'kind', 'confidence', 'ms', 'top probabilities or error'],
            array_map(fn (LabResult $r): array => [
                $r->driver,
                $r->kind ?? '-',
                $r->confidence === null ? '-' : number_format($r->confidence, 2),
                $r->ms,
                $r->error ?? self::top($r->probabilities),
            ], $lab->run($text, $drivers)),
        );

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $drivers
     */
    private function evaluate(ClassifierLab $lab, string $file, array $drivers): int
    {
        if (! is_file($file)) {
            $this->error("No file at {$file}");

            return self::FAILURE;
        }

        $report = $lab->evaluate($file, $drivers);

        $this->table(
            ['expected', 'message', ...$drivers],
            array_map(fn (array $case): array => [
                $case['expected'],
                mb_strimwidth($case['text'], 0, 40, '...'),
                ...array_map(static fn (LabResult $r): string => $r->error !== null ? 'error' : (($r->kind === $case['expected'] ? 'ok ' : 'NO ').($r->kind ?? '-')), $case['results']),
            ], $report['cases']),
        );

        $this->table(
            ['driver', 'correct', 'accuracy', 'errors', 'avg ms'],
            array_map(static fn (string $driver, array $row): array => [
                $driver,
                "{$row['correct']}/{$row['total']}",
                $row['total'] > 0 ? round(100 * $row['correct'] / $row['total']).'%' : '-',
                $row['errors'],
                $row['avg_ms'],
            ], array_keys($report['summary']), $report['summary']),
        );

        return self::SUCCESS;
    }

    /**
     * @param  array<string, float>  $probabilities
     */
    private static function top(array $probabilities): string
    {
        return implode('  ', array_map(
            static fn (string $kind, float $p): string => $kind.' '.number_format($p, 2),
            array_slice(array_keys($probabilities), 0, 3),
            array_slice(array_values($probabilities), 0, 3),
        ));
    }
}
