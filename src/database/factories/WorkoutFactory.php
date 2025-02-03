// database/factories/WorkoutFactory.php
<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class WorkoutFactory extends Factory
{
    public function definition(): array
    {
        return [
            'raw_input' => $this->faker->sentence(),
            'parsed_data' => [
                'exercises' => [
                    [
                        'name' => 'Bench Press',
                        'sets' => [
                            [
                                'reps' => 5,
                                'weight' => 100,
                                'notes' => null
                            ]
                        ]
                    ]
                ]
            ],
            'performed_at' => now(),
        ];
    }
}
