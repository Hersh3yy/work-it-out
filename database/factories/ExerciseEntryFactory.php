<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ExerciseEntry;
use App\Models\WorkoutSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ExerciseEntry> */
class ExerciseEntryFactory extends Factory
{
    private const EXERCISES = [
        'Barbell Squat', 'Deadlift', 'Bench Press', 'Overhead Press',
        'Barbell Row', 'Pull-Up', 'Dumbbell Curl', 'Tricep Dip',
        'Leg Press', 'Romanian Deadlift', 'Incline Bench Press',
        'Lat Pulldown', 'Cable Row', 'Face Pull', 'Hip Thrust',
        'Treadmill Run', 'Cycling', 'Jump Rope',
    ];

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'workout_session_id' => WorkoutSession::factory(),
            'exercise_name'      => fake()->randomElement(self::EXERCISES),
            'sets'               => fake()->numberBetween(2, 5),
            'reps'               => fake()->numberBetween(5, 15),
            'weight_kg'          => fake()->optional(0.8)->randomFloat(1, 20, 140),
            'duration_seconds'   => null,
            'distance_meters'    => null,
            'notes'              => null,
            'sort_order'         => 0,
        ];
    }

    public function cardio(): static
    {
        return $this->state(fn (array $attributes): array => [
            'exercise_name'    => fake()->randomElement(['Treadmill Run', 'Cycling', 'Jump Rope']),
            'sets'             => null,
            'reps'             => null,
            'weight_kg'        => null,
            'duration_seconds' => fake()->numberBetween(600, 3600),
            'distance_meters'  => fake()->optional()->numberBetween(500, 10000),
        ]);
    }
}
