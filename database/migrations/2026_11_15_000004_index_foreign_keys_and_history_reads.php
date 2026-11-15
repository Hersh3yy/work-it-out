<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Postgres does not index foreign keys by itself, and history is read by
 * person and date (audit 2026-11-14 item 8). Additive only.
 */
return new class extends Migration
{
    /** @var array<string, list<list<string>>> table => the column lists to index */
    private const array INDEXES = [
        'workout_sessions' => [['user_id', 'logged_at']],
        'body_weight_logs' => [['user_id', 'logged_at']],
        'exercise_entries' => [['workout_session_id'], ['activity_log_id']],
        'diary_entries' => [['activity_log_id']],
        'limitations' => [['activity_log_id']],
        'limitation_updates' => [['activity_log_id']],
        'channel_identities' => [['user_id']],
        'channel_link_codes' => [['user_id']],
        'login_codes' => [['user_id']],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            Schema::table($table, static function (Blueprint $blueprint) use ($indexes): void {
                foreach ($indexes as $columns) {
                    $blueprint->index($columns);
                }
            });
        }
    }

    public function down(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            Schema::table($table, static function (Blueprint $blueprint) use ($indexes): void {
                foreach ($indexes as $columns) {
                    $blueprint->dropIndex($columns);
                }
            });
        }
    }
};
