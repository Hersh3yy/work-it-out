<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ExperienceLevel;
use App\Enums\PrimaryGoal;
use App\Enums\TrainerPersona;
use App\Models\User;
use App\Models\WorkoutSession;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $demo = User::factory()->create([
            'name' => 'Ruby Demo',
            'email' => 'demo@trainwithruby.app',
            'password' => 'password',
            'trainer_persona' => TrainerPersona::LtSurge->value,
            'experience_level' => ExperienceLevel::Intermediate->value,
            'training_days_per_week' => 4,
            'primary_goal' => PrimaryGoal::BuildMuscle->value,
            'goal_description' => 'I want to squat 140kg by December',
            'goal_deadline' => now()->endOfYear()->toDateString(),
            'current_weight_kg' => 82.5,
            'target_weight_kg' => 85.0,
        ]);

        $this->seedWorkouts($demo);
        $this->seedBodyWeight($demo);
    }

    private function seedWorkouts(User $user): void
    {
        $exercises = [
            ['Barbell Squat', 5, 5, 100.0],
            ['Deadlift', 4, 3, 120.0],
            ['Bench Press', 4, 8, 80.0],
            ['Overhead Press', 3, 8, 55.0],
            ['Barbell Row', 4, 8, 70.0],
            ['Pull-Up', 3, 8, null],
            ['Romanian Deadlift', 3, 10, 80.0],
            ['Incline Bench Press', 3, 10, 65.0],
        ];

        for ($i = 14; $i >= 0; $i--) {
            $day = now()->subDays($i);

            // Skip Wednesdays and Sundays to simulate rest days
            if (in_array($day->dayOfWeek, [0, 3], true)) {
                continue;
            }

            $completed = $i > 2 || rand(0, 10) > 3;

            /** @var WorkoutSession $session */
            $session = $user->workoutSessions()->create([
                'logged_at' => $day->setTime(7, 0),
                'duration_minutes' => rand(45, 75),
                'perceived_exertion' => rand(6, 9),
                'energy_level' => rand(3, 5),
                'completed_planned' => $completed,
                'notes' => match ($i) {
                    0 => 'Felt strong today. New squat PR incoming.',
                    3 => 'Shoulder felt tight during OHP. Backed off weight.',
                    default => null,
                },
            ]);

            if ($completed) {
                shuffle($exercises);
                foreach (array_slice($exercises, 0, 4) as $sort => [$name, $sets, $reps, $weight]) {
                    $session->exerciseEntries()->create([
                        'exercise_name' => $name,
                        'sets' => $sets,
                        'reps' => $reps,
                        'weight_kg' => $weight,
                        'sort_order' => $sort,
                    ]);
                }
            }
        }
    }

    private function seedBodyWeight(User $user): void
    {
        $weights = [83.2, 82.8, 82.5, 82.7, 82.5];

        foreach ($weights as $i => $weight) {
            $user->bodyWeightLogs()->create([
                'logged_at' => now()->subDays((count($weights) - $i - 1) * 7)->toDateString(),
                'weight_kg' => $weight,
            ]);
        }
    }
}
