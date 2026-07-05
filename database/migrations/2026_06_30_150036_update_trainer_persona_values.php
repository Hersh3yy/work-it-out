<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Rename legacy trainer_persona values to the three-coach system.
 *
 * Legacy → new:
 *   general → lt_surge
 *   coach   → shen
 *
 * Rows that somehow already hold an unknown value default to lt_surge.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->where('trainer_persona', 'general')->update(['trainer_persona' => 'lt_surge']);
        DB::table('users')->where('trainer_persona', 'coach')->update(['trainer_persona' => 'shen']);
        DB::table('users')
            ->whereNotIn('trainer_persona', ['lt_surge', 'shen', 'latika'])
            ->update(['trainer_persona' => 'lt_surge']);
    }

    public function down(): void
    {
        DB::table('users')->where('trainer_persona', 'lt_surge')->update(['trainer_persona' => 'general']);
        DB::table('users')->where('trainer_persona', 'shen')->update(['trainer_persona' => 'coach']);
        DB::table('users')->where('trainer_persona', 'latika')->update(['trainer_persona' => 'general']);
    }
};
