<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

/*
 * Postgres does not index foreign keys by itself (audit 2026-11-14 item 8).
 * Every foreign key gets one, and history is read by person and date.
 */

it('indexes every foreign key', function (): void {
    $unindexed = DB::select(<<<'SQL'
        SELECT c.conrelid::regclass::text AS tbl, a.attname AS col
        FROM pg_constraint c
        JOIN pg_attribute a ON a.attrelid = c.conrelid AND a.attnum = c.conkey[1]
        WHERE c.contype = 'f'
          AND NOT EXISTS (SELECT 1 FROM pg_index i WHERE i.indrelid = c.conrelid AND i.indkey[0] = c.conkey[1])
        SQL);

    expect(array_map(static fn (object $row): string => "{$row->tbl}.{$row->col}", $unindexed))->toBe([]);
})->skip(fn (): bool => DB::getDriverName() !== 'pgsql', 'Reads the Postgres catalogue.');

it('reads workouts and body weight by person and date from an index', function (string $table): void {
    $indexes = collect(DB::select('SELECT indexdef FROM pg_indexes WHERE tablename = ?', [$table]))->pluck('indexdef');

    expect($indexes->contains(static fn (string $def): bool => str_contains($def, '(user_id, logged_at)')))->toBeTrue();
})->with(['workout_sessions', 'body_weight_logs'])
    ->skip(fn (): bool => DB::getDriverName() !== 'pgsql', 'Reads the Postgres catalogue.');
