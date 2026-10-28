<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The written profile: short observations about the user, each citing the
 * logs it came from. Every update is a new row, so the history shows how the
 * picture changed. Kept apart from what the user entered (PLAN.md section 4).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profile_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20);
            $table->json('notes');
            $table->ulid('based_on_log_id')->nullable();
            $table->string('model', 100)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_versions');
    }
};
