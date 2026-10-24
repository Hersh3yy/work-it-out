<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every profile question the app asked a user, and whether it was answered or
 * skipped. Drives "at most one question a day" and "do not repeat a skipped
 * question for a week", and keeps the history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profile_prompts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('question_key', 40);
            $table->timestamp('asked_at');
            $table->timestamp('answered_at')->nullable();
            $table->timestamp('skipped_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'asked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_prompts');
    }
};
