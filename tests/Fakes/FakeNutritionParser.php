<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Contracts\Ai\NutritionParser;

/**
 * In-memory NutritionParser for tests.
 */
final class FakeNutritionParser implements NutritionParser
{
    /** @var list<string> */
    public array $calls = [];

    /**
     * @param  array{food_name?: string, calories?: int|null, protein_g?: float|null, carbs_g?: float|null, fat_g?: float|null, meal_type?: string|null}  $result
     */
    public function __construct(
        private readonly array $result = [],
    ) {}

    public function parse(string $rawText): array
    {
        $this->calls[] = $rawText;

        return $this->result + [
            'food_name' => $rawText,
            'calories' => null,
            'protein_g' => null,
            'carbs_g' => null,
            'fat_g' => null,
            'meal_type' => null,
        ];
    }
}
