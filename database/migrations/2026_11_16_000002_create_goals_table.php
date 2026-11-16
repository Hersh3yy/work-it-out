<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A goal in the person's words with the day it is for. Undoing the message
 * that stated it removes it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('activity_log_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('kind', 20);
            $table->string('name', 60);
            $table->string('description', 255);
            $table->date('target_on')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'target_on']);
            $table->index('activity_log_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goals');
    }
};
