<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->unsignedTinyInteger('daily_coaching_hour')->nullable()->default(8)->after('trainer_persona');
        });

        Schema::table('coach_turns', function (Blueprint $table): void {
            $table->string('kind', 10)->default('asked')->after('depth');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('daily_coaching_hour');
        });

        Schema::table('coach_turns', function (Blueprint $table): void {
            $table->dropColumn('kind');
        });
    }
};
