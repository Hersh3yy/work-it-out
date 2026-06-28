<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // DECIMAL(4,2) has a max of 99.99. The adherence rate can be 100.00
            // so we widen to DECIMAL(5,2) which supports up to 999.99.
            $table->decimal('weekly_adherence_rate', 5, 2)->default(0.00)->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->decimal('weekly_adherence_rate', 4, 2)->default(0.00)->change();
        });
    }
};
