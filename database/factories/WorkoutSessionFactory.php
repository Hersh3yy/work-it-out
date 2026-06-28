<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use App\Models\WorkoutSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WorkoutSession> */
class WorkoutSessionFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id'           => User::factory(),
            'logged_at'         => fake()->dateTimeBetween('-30 days', 'now'),
            'duration_minutes'  => fake()->numberBetween(20, 90),
            'perceived_exertion' => fake()->numberBetween(5, 9),
            'energy_level'      => fake()->numberBetween(2, 5),
            'notes'             => fake()->optional(0.4)->sentence(),
            'completed_planned' => fake()->boolean(80),
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'completed_planned' => true,
        ]);
    }

    public function skipped(): static
    {
        return $this->state(fn (array $attributes): array => [
            'completed_planned' => false,
        ]);
    }
}
