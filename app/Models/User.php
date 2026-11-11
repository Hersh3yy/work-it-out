<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ExperienceLevel;
use App\Enums\PrimaryGoal;
use App\Enums\TrainerPersona;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
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
    'daily_coaching_hour',
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
    'rpg_strength',
    'rpg_stamina',
    'rpg_vitality',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasConversations, HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'trainer_persona' => TrainerPersona::class,
            'daily_coaching_hour' => 'integer',
            'experience_level' => ExperienceLevel::class,
            'primary_goal' => PrimaryGoal::class,
            'goal_deadline' => 'date',
            'target_weight_kg' => 'decimal:2',
            'current_weight_kg' => 'decimal:2',
            'weekly_adherence_rate' => 'decimal:2',
            'last_active_at' => 'datetime',
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

    /** @return HasMany<CustomRpgStat, $this> */
    public function customRpgStats(): HasMany
    {
        return $this->hasMany(CustomRpgStat::class)->orderByDesc('last_updated_at');
    }

    /** @return HasMany<ChannelIdentity, $this> */
    public function channelIdentities(): HasMany
    {
        return $this->hasMany(ChannelIdentity::class);
    }

    /** @return HasMany<Limitation, $this> */
    public function limitations(): HasMany
    {
        return $this->hasMany(Limitation::class);
    }

    /**
     * Newest first.
     *
     * @return HasMany<CoachTurn, $this>
     */
    public function waitingMessages(): HasMany
    {
        return $this->hasMany(WaitingMessage::class);
    }

    /**
     * Newest first.
     *
     * @return HasMany<CoachTurn, $this>
     */
    public function coachTurns(): HasMany
    {
        return $this->hasMany(CoachTurn::class)->latest('created_at')->latest('id');
    }

    /** @return HasMany<ActivityLog, $this> */
    /**
     * The written profile, newest version first.
     *
     * @return HasMany<ProfileVersion, $this>
     */
    public function profileVersions(): HasMany
    {
        return $this->hasMany(ProfileVersion::class)->latest('id');
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class)->latest();
    }

    /** @return HasMany<DiaryEntry, $this> */
    public function diaryEntries(): HasMany
    {
        return $this->hasMany(DiaryEntry::class)->latest();
    }

    /**
     * Who gets the morning message now: linked to a chat, heard from within
     * coach.daily.quiet_after_days, their hour in coach.daily.timezone, and
     * not sent today.
     *
     * @param  Builder<User>  $query
     */
    public function scopeDueForMorningMessage(Builder $query): void
    {
        $hour = (int) now()->timezone((string) config('coach.daily.timezone'))->format('G');
        $heardSince = now()->subDays((int) config('coach.daily.quiet_after_days'));

        $query
            ->where('daily_coaching_hour', $hour)
            ->whereHas('channelIdentities')
            ->where(static fn (Builder $q) => $q
                ->whereHas('activityLogs', static fn (Builder $l) => $l->where('created_at', '>=', $heardSince))
                ->orWhereHas('coachTurns', static fn (Builder $t) => $t->where('kind', CoachTurn::ASKED)->where('created_at', '>=', $heardSince)))
            ->whereDoesntHave('coachTurns', static fn (Builder $t) => $t->where('kind', CoachTurn::DAILY)->where('created_at', '>=', today()));
    }

    /**
     * Last 7 days of workout sessions, eager-loaded with exercise entries.
     *
     * @return Collection<int, WorkoutSession>
     */
    public function recentWorkouts(): Collection
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
            'adherence_rate' => $this->weekly_adherence_rate,
            'streak' => $this->current_streak_days,
            'total_volume_kg' => round($totalVolume, 2),
        ];
    }
}
