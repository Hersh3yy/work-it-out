<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

/**
 * Parses free-text food descriptions into structured macro data.
 *
 * Returns a typed, validated response schema instead of raw JSON parsing,
 * using the AI SDK's structured output feature.
 */
final class NutritionParserAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): string
    {
        return <<<'INSTRUCTIONS'
You are a nutrition data extraction assistant. Given a free-text description of food or a meal, extract structured nutritional data.

Rules:
- Extract food_name, calories, protein_g, carbs_g, fat_g, and meal_type.
- meal_type must be one of: breakfast, lunch, dinner, snack, supplement.
- If a value cannot be reliably determined from the text, return null for that field.
- Do NOT invent values. Only return numbers you are confident about from the text or from widely-known nutritional facts.
- food_name should be a clean, concise name (e.g. "3 scrambled eggs" not the raw input).
- Return ONLY the structured data. No explanation.
INSTRUCTIONS;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'food_name'  => $schema->string()->required(),
            'calories'   => $schema->integer()->nullable(),
            'protein_g'  => $schema->number()->nullable(),
            'carbs_g'    => $schema->number()->nullable(),
            'fat_g'      => $schema->number()->nullable(),
            'meal_type'  => $schema->string()->nullable(),
        ];
    }
}
