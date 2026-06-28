<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nutrition_logs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('logged_at');
            $table->string('meal_type');
            $table->string('food_name', 150);
            $table->smallInteger('calories')->unsigned()->nullable();
            $table->decimal('protein_g', 5, 1)->nullable();
            $table->decimal('carbs_g', 5, 1)->nullable();
            $table->decimal('fat_g', 5, 1)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nutrition_logs');
    }
};
