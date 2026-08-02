<?php

declare(strict_types=1);

namespace App\Contracts\Ai;

/**
 * Port for converting free-text food descriptions into macro data.
 *
 * @see https://refactoring.guru/design-patterns/adapter
 */
interface NutritionParser
{
    /**
     * @return array{food_name: string, calories: int|null, protein_g: float|null, carbs_g: float|null, fat_g: float|null, meal_type: string|null}
     */
    public function parse(string $rawText): array;
}
