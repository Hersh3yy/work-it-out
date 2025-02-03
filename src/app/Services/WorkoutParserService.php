<?php

namespace App\Services;

use OpenAI\Laravel\Facades\OpenAI;

class WorkoutParserService
{
    public function parse(string $description)
    {
        $response = OpenAI::chat()->create([
            'model' => 'gpt-4o-mini',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => <<<EOT
                    You are a fitness data parser. Convert workout descriptions into structured JSON data.
                    Expected format:
                    {
                        "exercises": [
                            {
                                "name": "exercise name",
                                "sets": [
                                    {
                                        "reps": number,
                                        "weight": number in kg,
                                        "notes": "optional notes"
                                    }
                                ]
                            }
                        ]
                    }
                    EOT
                ],
                [
                    'role' => 'user',
                    'content' => $description
                ]
            ],
            'response_format' => ['type' => 'json_object']
        ]);

        return json_decode($response->choices[0]->message->content, true);
    }
}
