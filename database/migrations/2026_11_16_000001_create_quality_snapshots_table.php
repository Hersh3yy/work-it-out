<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per person, quality and day: the level points earned that day,
 * the power and the readiness as of that day. Level is the sum of the points.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quality_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('quality', 20);
            $table->date('day');
            $table->unsignedSmallInteger('level_points')->default(0);
            $table->decimal('power', 6, 2)->nullable();
            $table->unsignedTinyInteger('readiness');
            $table->timestamps();

            $table->unique(['user_id', 'quality', 'day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quality_snapshots');
    }
};
