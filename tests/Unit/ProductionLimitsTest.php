<?php

declare(strict_types=1);

/*
 * Production hardening (audit 2026-11-08 tier 1 items 8 and 12): the worker
 * can be stopped and timed out (pcntl), a hung model cannot outlast the
 * worker, and no local service listens beyond this laptop.
 */

function workerTimeout(): int
{
    preg_match('/queue:work[^\n]*--timeout=(\d+)/', (string) file_get_contents(base_path('docker/supervisor/supervisord.conf')), $m);

    return (int) ($m[1] ?? 0);
}

it('builds the production image with pcntl, so redeploys stop the worker and the poller cleanly', function (): void {
    expect(file_get_contents(base_path('Dockerfile')))->toMatch('/install-php-extensions[^#]*\bpcntl\b/');
});

it('lets every AI call of a job time out, twice over for the fallback, before the worker does', function (): void {
    $slowestPath = array_sum(config('ai.timeouts')) * 2;

    expect(workerTimeout())->toBeGreaterThan($slowestPath)
        ->and(config('queue.connections.database.retry_after'))->toBeGreaterThan(workerTimeout());
});

it('publishes local ports on 127.0.0.1 only', function (string $file): void {
    preg_match_all('/^\s*-\s*"([^"]+:\d+)"\s*$/m', (string) file_get_contents(base_path($file)), $m);

    expect($m[1])->not->toBeEmpty()
        ->each->toStartWith('127.0.0.1:');
})->with(['compose.yaml', 'compose.override.yaml']);

it('starts Redis with a password', function (): void {
    expect(file_get_contents(base_path('compose.yaml')))->toContain('--requirepass');
});
