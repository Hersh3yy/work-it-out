<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The app's timezone moved from UTC to Europe/Amsterdam on 2026-11-15.
 * Laravel stores timestamps in the app's timezone without an offset, so the
 * rows written before are UTC and would read an hour or two early. This
 * rewrites every timestamp column once, honouring summer time per row.
 * Dates (logged_on, started_on) were already the person's day and stay.
 * SQLite databases here are test databases, always fresh: nothing to move.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->convert(from: 'UTC', to: 'Europe/Amsterdam');
    }

    public function down(): void
    {
        $this->convert(from: 'Europe/Amsterdam', to: 'UTC');
    }

    private function convert(string $from, string $to): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach ($this->timestampColumns() as $table => $columns) {
            $assignments = implode(', ', array_map(
                static fn (string $column): string => sprintf('"%1$s" = ("%1$s" AT TIME ZONE \'%2$s\') AT TIME ZONE \'%3$s\'', $column, $from, $to),
                $columns,
            ));

            DB::statement(sprintf('UPDATE "%s" SET %s', $table, $assignments));
        }
    }

    /**
     * @return array<string, list<string>> table => its timestamp columns
     */
    private function timestampColumns(): array
    {
        $rows = DB::select("SELECT table_name, column_name FROM information_schema.columns WHERE table_schema = current_schema() AND data_type = 'timestamp without time zone' ORDER BY table_name, column_name");

        $columns = [];

        foreach ($rows as $row) {
            $columns[$row->table_name][] = $row->column_name;
        }

        return $columns;
    }
};
