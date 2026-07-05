<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stores the three-coach reaction to each logged activity.
 *
 * Uses a polymorphic relation so it can attach to workout_sessions,
 * nutrition_logs, body_weight_logs, or a generic smart-log text.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_feedbacks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->nullableMorphs('loggable');
            $table->string('raw_message')->nullable();
            $table->string('log_summary');
            $table->text('lt_surge')->nullable();
            $table->text('shen')->nullable();
            $table->text('latika')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_feedbacks');
    }
};
