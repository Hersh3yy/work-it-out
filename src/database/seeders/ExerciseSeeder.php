<?php

namespace Database\Seeders;

use App\Models\Exercise;
use Illuminate\Database\Seeder;

class ExerciseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $exercises = [
            ['name' => 'Bench Press', 'category' => 'chest'],
            ['name' => 'Squat', 'category' => 'legs'],
            ['name' => 'Deadlift', 'category' => 'back'],
            ['name' => 'Overhead Press', 'category' => 'shoulders'],
            ['name' => 'Barbell Row', 'category' => 'back'],
        ];

        foreach ($exercises as $exercise) {
            Exercise::create($exercise);
        }
    }
}
