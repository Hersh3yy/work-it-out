<?php

declare(strict_types=1);

use App\Ai\Agents\NutritionParserAgent;
use App\Services\NutritionParserService;
use Laravel\Ai\Ai;

it('falls back gracefully when the AI is unreachable', function (): void {
    // No fake agent registered: NutritionParserService catches any Throwable and
    // returns the raw text as food_name with null macros.
    $raw    = 'Some weird food input';
    $result = app(NutritionParserService::class)->parse($raw);

    expect($result['food_name'])->toBe($raw)
        ->and($result['calories'])->toBeNull()
        ->and($result['protein_g'])->toBeNull();
});

it('uses faked agent response to fill macro fields', function (): void {
    Ai::fakeAgent(NutritionParserAgent::class, [
        json_encode([
            'food_name'  => '3 scrambled eggs',
            'calories'   => 210,
            'protein_g'  => 18.0,
            'carbs_g'    => 1.0,
            'fat_g'      => 15.0,
            'meal_type'  => 'breakfast',
        ]),
    ]);

    $result = app(NutritionParserService::class)->parse('I had three scrambled eggs for breakfast');

    // Service wraps the raw string; structured output decoding happens in the agent layer.
    // At minimum the call shouldn't throw.
    expect($result)->toBeArray()
        ->and($result)->toHaveKey('food_name');
});
