<?php

declare(strict_types=1);

namespace App\Services;

use App\Ai\Agents\NutritionParserAgent;
use Throwable;

/**
 * Wraps the NutritionParserAgent to provide a safe, fallback-capable
 * interface for converting free-text food descriptions into macro data.
 */
final class NutritionParserService
{
    /**
     * Parse a free-text food description into structured macro data.
     *
     * On any AI failure the raw text is returned as the food_name with
     * all macro fields as null — the user can fill them in manually.
     *
     * @return array{food_name: string, calories: int|null, protein_g: float|null, carbs_g: float|null, fat_g: float|null, meal_type: string|null}
     */
    public function parse(string $rawText): array
    {
        try {
            $result = NutritionParserAgent::make()->prompt($rawText);

            return [
                'food_name'  => (string) ($result['food_name'] ?? $rawText),
                'calories'   => isset($result['calories']) ? (int) $result['calories'] : null,
                'protein_g'  => isset($result['protein_g']) ? (float) $result['protein_g'] : null,
                'carbs_g'    => isset($result['carbs_g']) ? (float) $result['carbs_g'] : null,
                'fat_g'      => isset($result['fat_g']) ? (float) $result['fat_g'] : null,
                'meal_type'  => $result['meal_type'] ?? null,
            ];
        } catch (Throwable) {
            return [
                'food_name'  => $rawText,
                'calories'   => null,
                'protein_g'  => null,
                'carbs_g'    => null,
                'fat_g'      => null,
                'meal_type'  => null,
            ];
        }
    }
}
