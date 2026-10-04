<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A log is a receipt, not coach feedback (PLAN.md section 4, 2026-10-04).
 *
 * activity_feedbacks held three coach reactions per log and an integer morph
 * id, which truncated the ULID keys of workout_sessions on MySQL. It becomes
 * activity_logs: one row per message, a ULID morph to what was logged, and the
 * open questions for values the user did not give. exercise_entries remember
 * which log added them, so an undo removes exactly those entries.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('diary_entries', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('activity_feedback_id');
        });

        Schema::dropIfExists('activity_feedbacks');

        Schema::create('activity_logs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('source', 20);
            $table->string('log_type', 20);
            $table->text('raw_message');
            $table->string('summary');
            $table->nullableUlidMorphs('loggable');
            $table->json('questions')->nullable();
            $table->date('logged_on');
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });

        Schema::table('diary_entries', function (Blueprint $table): void {
            $table->foreignUlid('activity_log_id')->nullable()->after('user_id')
                ->constrained()->cascadeOnDelete();
        });

        Schema::table('exercise_entries', function (Blueprint $table): void {
            $table->foreignUlid('activity_log_id')->nullable()->after('workout_session_id')
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('exercise_entries', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('activity_log_id');
        });

        Schema::table('diary_entries', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('activity_log_id');
        });

        Schema::dropIfExists('activity_logs');

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

        Schema::table('diary_entries', function (Blueprint $table): void {
            $table->foreignId('activity_feedback_id')->nullable()->after('user_id')
                ->constrained()->nullOnDelete();
        });
    }
};
