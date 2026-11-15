<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The nutrition log, the RPG stats and the old trainer chat's conversation
 * store were removed from the code on 2026-11-15. Their create migrations
 * are gone, so a fresh database never has them; this drops them where an
 * older database still does (production had no rows in any of them).
 */
return new class extends Migration
{
    private const array RPG_COLUMNS = ['rpg_strength', 'rpg_stamina', 'rpg_vitality'];

    public function up(): void
    {
        Schema::dropIfExists('nutrition_logs');
        Schema::dropIfExists('custom_rpg_stats');
        Schema::dropIfExists('agent_conversation_messages');
        Schema::dropIfExists('agent_conversations');

        $present = array_values(array_filter(self::RPG_COLUMNS, static fn (string $column): bool => Schema::hasColumn('users', $column)));

        if ($present !== []) {
            Schema::table('users', static fn (Blueprint $table) => $table->dropColumn($present));
        }
    }

    public function down(): void {}
};
