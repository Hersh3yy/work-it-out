<?php

declare(strict_types=1);

namespace App\Ai\Usage;

use App\Models\AiCallRecord;
use Carbon\CarbonInterface;
use InvalidArgumentException;

/**
 * Calls, tokens and estimated cost since a moment, grouped by user, job,
 * model or day. Cost is price per million tokens from config ai.prices;
 * a model without a price is listed as unpriced, never guessed.
 */
final readonly class UsageReport
{
    public const array GROUPS = ['user', 'job', 'model', 'day'];

    /**
     * @return array{rows: list<array<string, mixed>>, total: array<string, mixed>, unpriced: list<string>}
     */
    public function since(CarbonInterface $since, string $by): array
    {
        $rows = [];
        $unpriced = [];

        foreach (AiCallRecord::query()->with('user')->since($since)->orderBy('created_at')->cursor() as $call) {
            $key = $this->groupOf($call, $by);
            $row = $rows[$key] ?? ['group' => $key, 'calls' => 0, 'failed' => 0, 'input' => 0, 'output' => 0, 'tokens' => 0, 'cost' => 0.0];
            $cost = $this->costOf($call);

            if ($cost === null && $call->model !== null && $call->totalTokens() > 0) {
                $unpriced[$call->model] = $call->model;
            }

            $row['calls']++;
            $row['failed'] += $call->failed ? 1 : 0;
            $row['input'] += $call->input_tokens;
            $row['output'] += $call->output_tokens;
            $row['tokens'] += $call->totalTokens();
            $row['cost'] += $cost ?? 0.0;
            $rows[$key] = $row;
        }

        $total = ['group' => 'total', 'calls' => 0, 'failed' => 0, 'input' => 0, 'output' => 0, 'tokens' => 0, 'cost' => 0.0];

        foreach ($rows as $row) {
            foreach (['calls', 'failed', 'input', 'output', 'tokens', 'cost'] as $column) {
                $total[$column] += $row[$column];
            }
        }

        return ['rows' => array_map(self::rounded(...), array_values($rows)), 'total' => self::rounded($total), 'unpriced' => array_values($unpriced)];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private static function rounded(array $row): array
    {
        return array_merge($row, ['cost' => round($row['cost'], 6)]);
    }

    private function groupOf(AiCallRecord $call, string $by): string
    {
        return match ($by) {
            'user' => $call->user?->email ?? 'no user',
            'job' => $call->job(),
            'model' => $call->model ?? 'unknown model',
            'day' => $call->created_at->toDateString(),
            default => throw new InvalidArgumentException('Group by one of: '.implode(', ', self::GROUPS)),
        };
    }

    /** USD, or null when the model has no price in config. */
    private function costOf(AiCallRecord $call): ?float
    {
        $price = config('ai.prices')[$call->model] ?? null;

        if (! is_array($price) || ! is_numeric($price['input'] ?? null) || ! is_numeric($price['output'] ?? null)) {
            return null;
        }

        return $call->input_tokens / 1_000_000 * (float) $price['input']
            + $call->output_tokens / 1_000_000 * (float) $price['output'];
    }
}
