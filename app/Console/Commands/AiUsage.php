<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Ai\Usage\UsageReport;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

final class AiUsage extends Command
{
    protected $signature = 'ai:usage {--since=30d : A span like 7d, 24h, or a date} {--by=user : Group by user, job, model or day}';

    protected $description = 'Calls, tokens and estimated cost per user, job, model or day (read-only)';

    public function handle(UsageReport $report): int
    {
        $by = (string) $this->option('by');

        if (! in_array($by, UsageReport::GROUPS, true)) {
            $this->error('Group by one of: '.implode(', ', UsageReport::GROUPS).'.');

            return self::FAILURE;
        }

        $since = self::sinceFrom((string) $this->option('since'));
        $usage = $report->since($since, $by);

        $this->info(sprintf('AI usage since %s, by %s', $since->toDateTimeString(), $by));
        $this->table(
            [ucfirst($by), 'Calls', 'Failed', 'Input tokens', 'Output tokens', 'Tokens', 'Est. cost USD'],
            array_map(self::row(...), [...$usage['rows'], $usage['total']]),
        );

        if ($usage['unpriced'] !== []) {
            $this->warn('No price in config/ai.php for: '.implode(', ', $usage['unpriced']).'. Their tokens are counted, their cost is not.');
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<string>
     */
    private static function row(array $row): array
    {
        return [
            (string) $row['group'],
            (string) $row['calls'],
            (string) $row['failed'],
            number_format($row['input']),
            number_format($row['output']),
            number_format($row['tokens']),
            number_format($row['cost'], 4),
        ];
    }

    private static function sinceFrom(string $option): CarbonImmutable
    {
        if (preg_match('/^(\d+)([dh])$/', $option, $m)) {
            return $m[2] === 'd' ? CarbonImmutable::now()->subDays((int) $m[1]) : CarbonImmutable::now()->subHours((int) $m[1]);
        }

        return CarbonImmutable::parse($option);
    }
}
