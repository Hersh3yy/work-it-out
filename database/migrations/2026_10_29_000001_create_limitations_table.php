<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Injuries and limitations the user told us about ("hurt my back, go slow
 * for 2 weeks"). User-stated facts, stored apart from the AI's notes; each
 * cites the message it came from and disappears with it. Only the user marks
 * one resolved (PLAN.md section 4, 2026-10-28 design).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('limitations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('activity_log_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('body_area', 20)->nullable();
            $table->string('severity', 20)->nullable();
            $table->string('note', 255);
            $table->date('started_on');
            $table->date('expected_until')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'resolved_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('limitations');
    }
};
