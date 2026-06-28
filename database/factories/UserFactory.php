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
            'name'                   => fake()->name(),
            'email'                  => fake()->unique()->safeEmail(),
            'email_verified_at'      => now(),
            'password'               => static::$password ??= Hash::make('password'),
            'remember_token'         => Str::random(10),
            'trainer_persona'        => fake()->randomElement(TrainerPersona::cases())->value,
            'experience_level'       => fake()->randomElement(ExperienceLevel::cases())->value,
            'training_days_per_week' => fake()->numberBetween(2, 5),
            'primary_goal'           => fake()->randomElement(PrimaryGoal::cases())->value,
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes): array => [
            'email_verified_at' => null,
        ]);
    }

    public function asGeneral(): static
    {
        return $this->state(fn (array $attributes): array => [
            'trainer_persona' => TrainerPersona::General->value,
        ]);
    }

    public function asCoach(): static
    {
        return $this->state(fn (array $attributes): array => [
            'trainer_persona' => TrainerPersona::Coach->value,
        ]);
    }
}
