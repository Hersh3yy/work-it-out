<?php

namespace Tests\Feature;

use App\Services\WorkoutParserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use OpenAI\Laravel\Facades\OpenAI;
use Tests\TestCase;

class WourkoutParserTest extends TestCase
{
    /**
     * A basic feature test example.
     */
    public function test_example(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    public function test_it_can_parse_workout_description()
    {
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
                                                'weight' => 100,
                                                'notes' => null
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

        $parser = app(WorkoutParserService::class);
        $result = $parser->parse('Did bench press 100kg for 5 reps');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('exercises', $result);
    }
}
