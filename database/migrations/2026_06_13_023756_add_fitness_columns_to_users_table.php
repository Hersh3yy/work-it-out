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
            $table->string('trainer_persona')->default('general')->after('remember_token');
            $table->string('experience_level')->default('beginner')->after('trainer_persona');
            $table->tinyInteger('training_days_per_week')->unsigned()->default(3)->after('experience_level');
            $table->string('primary_goal')->default('general_fitness')->after('training_days_per_week');
            $table->text('goal_description')->nullable()->after('primary_goal');
            $table->date('goal_deadline')->nullable()->after('goal_description');
            $table->decimal('target_weight_kg', 5, 2)->nullable()->after('goal_deadline');
            $table->decimal('current_weight_kg', 5, 2)->nullable()->after('target_weight_kg');
            // Computed and cached by the UpdateUserStats job after each workout save.
            $table->decimal('weekly_adherence_rate', 5, 2)->default(0.00)->after('current_weight_kg');
            $table->smallInteger('current_streak_days')->unsigned()->default(0)->after('weekly_adherence_rate');
            $table->timestamp('last_active_at')->nullable()->after('current_streak_days');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'trainer_persona',
                'experience_level',
                'training_days_per_week',
                'primary_goal',
                'goal_description',
                'goal_deadline',
                'target_weight_kg',
                'current_weight_kg',
                'weekly_adherence_rate',
                'current_streak_days',
                'last_active_at',
            ]);
        });
    }
};
