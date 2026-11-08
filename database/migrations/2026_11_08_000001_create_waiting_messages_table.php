<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A message the AI could not handle when it arrived (overloaded, down).
 * Kept so it is answered later instead of lost; a job retries it and
 * replies in the chat by itself (docs/exploration-2026-11-07.md, section 2).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('waiting_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 20);
            $table->string('chat_id', 64);
            $table->string('source', 20);
            $table->text('text');
            $table->string('status', 12)->default('waiting');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waiting_messages');
    }
};
