<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AI-synthesised diary entries — one per smart-log call.
 *
 * The AI writes a short narrative sentence summarising the logged event
 * from a joint-coach perspective.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('diary_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('activity_feedback_id')->nullable()->constrained()->nullOnDelete();
            $table->text('content');
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diary_entries');
    }
};
