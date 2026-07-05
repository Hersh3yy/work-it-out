<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dynamic, activity-specific RPG stats that the AI spawns on-the-fly.
 *
 * Examples: "Bench Press Peak", "Padel Stamina", "Clean Food Harmony".
 * Up to 10 active stats per user; oldest are soft-purged by the update job.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_rpg_stats', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('value')->default(0);
            $table->unsignedTinyInteger('level')->default(1);
            $table->string('unit')->default('score');
            $table->string('category')->default('strength');
            $table->string('change_reason')->nullable();
            $table->timestamp('last_updated_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_rpg_stats');
    }
};
