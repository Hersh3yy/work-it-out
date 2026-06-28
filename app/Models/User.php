<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ExperienceLevel;
use App\Enums\PrimaryGoal;
use App\Enums\TrainerPersona;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Ai\Concerns\HasConversations;
use Laravel\Sanctum\HasApiTokens;

#[Fillable([
    'name',
    'email',
    'password',
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
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasConversations, HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at'      => 'datetime',
            'password'               => 'hashed',
            'trainer_persona'        => TrainerPersona::class,
            'experience_level'       => ExperienceLevel::class,
            'primary_goal'           => PrimaryGoal::class,
            'goal_deadline'          => 'date',
            'target_weight_kg'       => 'decimal:2',
            'current_weight_kg'      => 'decimal:2',
            'weekly_adherence_rate'  => 'decimal:2',
            'last_active_at'         => 'datetime',
        ];
    }

    /** @return HasMany<WorkoutSession, $this> */
    public function workoutSessions(): HasMany
    {
        return $this->hasMany(WorkoutSession::class);
    }

    /** @return HasMany<NutritionLog, $this> */
    public function nutritionLogs(): HasMany
    {
        return $this->hasMany(NutritionLog::class);
    }

    /** @return HasMany<BodyWeightLog, $this> */
    public function bodyWeightLogs(): HasMany
    {
        return $this->hasMany(BodyWeightLog::class);
    }

    /**
     * Last 7 days of workout sessions, eager-loaded with exercise entries.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, WorkoutSession>
     */
    public function recentWorkouts(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->workoutSessions()
            ->with('exerciseEntries')
            ->lastNDays(7)
            ->get();
    }

    /**
     * Summary stats for the current ISO week.
     *
     * @return array{sessions_this_week: int, adherence_rate: float|string, streak: int, total_volume_kg: float}
     */
    public function weeklyStats(): array
    {
        $sessions = $this->workoutSessions()->thisWeek()->with('exerciseEntries')->get();

        $totalVolume = $sessions->sum(
            fn (WorkoutSession $session): float => (float) $session->totalVolumeKg
        );

        return [
            'sessions_this_week' => $sessions->count(),
            'adherence_rate'     => $this->weekly_adherence_rate,
            'streak'             => $this->current_streak_days,
            'total_volume_kg'    => round($totalVolume, 2),
        ];
    }
}
