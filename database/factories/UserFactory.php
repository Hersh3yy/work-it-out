<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ExperienceLevel;
use App\Enums\PrimaryGoal;
use App\Enums\TrainerPersona;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/** @extends Factory<User> */
class UserFactory extends Factory
{
    protected static ?string $password;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'trainer_persona' => fake()->randomElement(TrainerPersona::cases())->value,
            'experience_level' => fake()->randomElement(ExperienceLevel::cases())->value,
            'training_days_per_week' => fake()->numberBetween(2, 5),
            'primary_goal' => fake()->randomElement(PrimaryGoal::cases())->value,
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes): array => [
            'email_verified_at' => null,
        ]);
    }

    public function asLtSurge(): static
    {
        return $this->state(fn (array $attributes): array => [
            'trainer_persona' => TrainerPersona::LtSurge->value,
        ]);
    }

    public function asShen(): static
    {
        return $this->state(fn (array $attributes): array => [
            'trainer_persona' => TrainerPersona::Shen->value,
        ]);
    }

    public function asLatika(): static
    {
        return $this->state(fn (array $attributes): array => [
            'trainer_persona' => TrainerPersona::Latika->value,
        ]);
    }

    /** A fresh user who has answered no onboarding questions yet. */
    public function withoutProfile(): static
    {
        return $this->state(fn (array $attributes): array => [
            'experience_level' => null,
            'training_days_per_week' => 0,
            'primary_goal' => null,
            'goal_description' => null,
            'goal_deadline' => null,
            'target_weight_kg' => null,
            'current_weight_kg' => null,
        ]);
    }

    /** A user with every intake field answered. */
    public function withCompleteProfile(): static
    {
        return $this->state(fn (array $attributes): array => [
            'experience_level' => ExperienceLevel::Intermediate->value,
            'training_days_per_week' => 4,
            'primary_goal' => PrimaryGoal::BuildMuscle->value,
            'goal_description' => 'Add 5kg of lean mass before summer.',
            'goal_deadline' => now()->addMonths(6)->toDateString(),
            'target_weight_kg' => 82.5,
            'current_weight_kg' => 77.0,
        ]);
    }
}
