<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Ai\IntentClassifier;
use App\Exceptions\AiUnavailable;
use Illuminate\Console\Command;

/**
 * The intent classifier alone: one message, or a labelled file scored.
 *
 *   php artisan intent:try "my knee hurts"
 *   php artisan intent:try --file=tests/Evals/intent.txt
 */
final class IntentTry extends Command
{
    protected $signature = 'intent:try
        {text? : One message, as you would text it}
        {--file= : Labelled file, one "intent | message" per line}
        {--driver=laya : laya (local) or jev (hosted, needs TYPESAFE_API_KEY)}';

    protected $description = 'Ask the intent classifier what a message means, or score it on labelled messages';

    public function handle(IntentClassifier $classifier): int
    {
        $driver = (string) $this->option('driver');

        if ($file = $this->option('file')) {
            $report = $classifier->evaluate((string) $file, $driver);

            $this->table(['expected', 'got', 'sure', 'message'], array_map(static fn (array $c): array => [
                $c['expected'],
                $c['error'] !== null ? 'error' : (($c['got'] === $c['expected'] ? 'ok ' : 'NO ').$c['got']),
                $c['confidence'] ?? '-',
                mb_strimwidth($c['text'], 0, 45, '...'),
            ], $report['cases']));

            $this->info(sprintf('%d/%d correct (%d%%), %s ms per message on average', $report['correct'], $report['total'], $report['total'] ? round(100 * $report['correct'] / $report['total']) : 0, $report['avg_ms']));

            return self::SUCCESS;
        }

        try {
            $result = $classifier->classify((string) $this->argument('text'), $driver);
        } catch (AiUnavailable $e) {
            $this->error($e->getMessage().' (is laya-serve running? make laya)');

            return self::FAILURE;
        }

        $this->info("{$result['intent']}  ({$result['confidence']} sure, {$result['ms']} ms)");

        foreach ($result['probabilities'] as $intent => $p) {
            $this->line(sprintf('  %-15s %s %.2f', $intent, str_repeat('#', (int) round($p * 30)), $p));
        }

        return self::SUCCESS;
    }
}
