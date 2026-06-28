<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\BodyWeightLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BodyWeightLog> */
class BodyWeightLogFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id'   => User::factory(),
            'logged_at' => fake()->dateTimeBetween('-60 days', 'now')->format('Y-m-d'),
            'weight_kg' => fake()->randomFloat(1, 65, 100),
            'notes'     => null,
        ];
    }
}
