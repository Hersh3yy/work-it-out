<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How an active limitation is going, in the person's own words ("back is 70%
 * of yesterday", "sitting hurts after 20 min"). Each update cites the message
 * it came from and disappears with it (PLAN.md section 4, 2026-11-01).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('limitation_updates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('limitation_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('activity_log_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('note', 255);
            $table->date('logged_on');
            $table->timestamps();

            $table->index(['limitation_id', 'logged_on']);
        });

        Schema::table('limitations', function (Blueprint $table): void {
            // The one-time red-flag screen: null (not answered), clear or flagged.
            $table->string('red_flags', 10)->nullable()->after('severity');
        });
    }

    public function down(): void
    {
        Schema::table('limitations', function (Blueprint $table): void {
            $table->dropColumn('red_flags');
        });

        Schema::dropIfExists('limitation_updates');
    }
};
