<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A kept message's reply is stored before it is sent, so a failed send is
 * retried on its own without handling the message (and logging it) again.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('waiting_messages', function (Blueprint $table): void {
            $table->text('reply')->nullable()->after('text');
        });
    }

    public function down(): void
    {
        Schema::table('waiting_messages', function (Blueprint $table): void {
            $table->dropColumn('reply');
        });
    }
};
