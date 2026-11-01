<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Questions asked to the coach and the answers given. Not facts: they never
 * feed the profile. The last few go back into the coach context so the coach
 * does not repeat itself within a session.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coach_turns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('question');
            $table->text('answer');
            $table->string('depth', 10)->nullable();
            $table->string('model', 80)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coach_turns');
    }
};
