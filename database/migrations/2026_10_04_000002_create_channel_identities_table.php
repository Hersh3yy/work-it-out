<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who a chat sender is. A sender with no row here is ignored: this table is
 * the allowlist (linked with `php artisan channel:link`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channel_identities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 20);
            $table->string('external_id', 64);
            $table->timestamps();

            $table->unique(['provider', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_identities');
    }
};
