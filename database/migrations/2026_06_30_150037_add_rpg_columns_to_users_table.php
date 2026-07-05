<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the three core RPG stat columns to the users table.
 *
 * strength → driven by Lt. Surge / resistance / lifting
 * stamina  → driven by Shen / cardio / endurance
 * vitality → driven by Latika / nutrition / recovery
 *
 * Each starts at 1 and can grow to 100. Updated by the smart-log pipeline.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->tinyInteger('rpg_strength')->unsigned()->default(1)->after('last_active_at');
            $table->tinyInteger('rpg_stamina')->unsigned()->default(1)->after('rpg_strength');
            $table->tinyInteger('rpg_vitality')->unsigned()->default(1)->after('rpg_stamina');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['rpg_strength', 'rpg_stamina', 'rpg_vitality']);
        });
    }
};
