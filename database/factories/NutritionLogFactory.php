<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\MealType;
use App\Models\NutritionLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<NutritionLog> */
class NutritionLogFactory extends Factory
{
    private const FOODS = [
        'Oats with banana'      => ['calories' => 350, 'protein_g' => 10, 'carbs_g' => 65, 'fat_g' => 5],
        'Chicken breast 200g'   => ['calories' => 330, 'protein_g' => 62, 'carbs_g' => 0, 'fat_g' => 7],
        'Greek yogurt'          => ['calories' => 130, 'protein_g' => 17, 'carbs_g' => 9, 'fat_g' => 2],
        'Brown rice 150g'       => ['calories' => 165, 'protein_g' => 3, 'carbs_g' => 35, 'fat_g' => 1],
        'Scrambled eggs x3'     => ['calories' => 210, 'protein_g' => 18, 'carbs_g' => 1, 'fat_g' => 15],
        'Protein shake'         => ['calories' => 160, 'protein_g' => 30, 'carbs_g' => 6, 'fat_g' => 2],
        'Salmon fillet 180g'    => ['calories' => 360, 'protein_g' => 40, 'carbs_g' => 0, 'fat_g' => 20],
        'Mixed salad'           => ['calories' => 80, 'protein_g' => 3, 'carbs_g' => 10, 'fat_g' => 2],
    ];

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $food    = fake()->randomElement(array_keys(self::FOODS));
        $macros  = self::FOODS[$food];
        $mealType = fake()->randomElement(MealType::cases());

        return [
            'user_id'   => User::factory(),
            'logged_at' => fake()->dateTimeBetween('-14 days', 'now'),
            'meal_type' => $mealType->value,
            'food_name' => $food,
            ...$macros,
            'notes'     => null,
        ];
    }
}
