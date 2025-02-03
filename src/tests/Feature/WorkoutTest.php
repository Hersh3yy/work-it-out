<?php

namespace Tests\Feature;

use Database\Seeders\ExerciseSeeder;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenAI\Laravel\Facades\OpenAI;

class WorkoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Seed basic exercises
        $this->seed(ExerciseSeeder::class);
    }

    public function test_can_create_workout()
    {
        // Mock OpenAI response
        OpenAI::fake([
            \OpenAI\Responses\Chat\CreateResponse::fake([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'exercises' => [
                                    [
                                        'name' => 'Bench Press',
                                        'sets' => [
                                            [
                                                'reps' => 5,
                                                'weight' => 60,
                                            ],
                                            [
                                                'reps' => 3,
                                                'weight' => 70,
                                            ],
                                            [
                                                'reps' => 1,
                                                'weight' => 80,
                                            ]
                                        ]
                                    ]
                                ]
                            ])
                        ]
                    ]
                ]
            ])
        ]);

        $response = $this->postJson('/api/workouts', [
            'description' => 'Did bench press today: 60kg for 5 reps, then 70kg for 3 reps, finally 80kg for 1 rep'
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'workout' => [
                    'id',
                    'raw_input',
                    'parsed_data',
                    'performed_at',
                    'created_at',
                    'updated_at'
                ]
            ]);
    }

    public function test_can_list_workouts()
    {
        // Create a few workouts first
        \App\Models\Workout::factory(3)->create();

        $response = $this->getJson('/api/workouts');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'workouts');
    }

    public function test_can_get_specific_workout()
    {
        $workout = \App\Models\Workout::factory()->create();

        $response = $this->getJson("/api/workouts/{$workout->id}");

        $response->assertStatus(200)
            ->assertJson([
                'workout' => [
                    'id' => $workout->id
                ]
            ]);
    }
}
