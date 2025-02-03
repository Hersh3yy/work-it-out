<?php

namespace Database\Seeders;

use App\Models\Workout;
use Illuminate\Database\Seeder;

class WorkoutSeeder extends Seeder
{
    public function run(): void
    {
        Workout::factory(10)
            ->create()
            ->each(function ($workout) {
                // Create some exercise sets for each workout
                // This assumes the parsed_data contains exercises
                if (isset($workout->parsed_data['exercises'])) {
                    foreach ($workout->parsed_data['exercises'] as $exerciseData) {
                        $exercise = \App\Models\Exercise::where('name', $exerciseData['name'])->first();

                        if ($exercise && isset($exerciseData['sets'])) {
                            foreach ($exerciseData['sets'] as $index => $setData) {
                                $workout->exerciseSets()->create([
                                    'exercise_id' => $exercise->id,
                                    'reps' => $setData['reps'] ?? null,
                                    'weight' => $setData['weight'] ?? null,
                                    'notes' => $setData['notes'] ?? null,
                                    'order' => $index
                                ]);
                            }
                        }
                    }
                }
            });
    }
}
