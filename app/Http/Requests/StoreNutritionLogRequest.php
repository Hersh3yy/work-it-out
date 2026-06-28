<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\MealType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreNutritionLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // Free-text mode: provide raw_text and the AI parser fills in macros.
            // Structured mode: provide food_name (+ optionals).
            'raw_text'   => ['nullable', 'string', 'max:500'],
            'food_name'  => ['required_without:raw_text', 'nullable', 'string', 'max:150'],
            'logged_at'  => ['nullable', 'date', 'before_or_equal:now'],
            'meal_type'  => ['nullable', Rule::enum(MealType::class)],
            'calories'   => ['nullable', 'integer', 'min:0', 'max:9999'],
            'protein_g'  => ['nullable', 'numeric', 'min:0', 'max:999'],
            'carbs_g'    => ['nullable', 'numeric', 'min:0', 'max:999'],
            'fat_g'      => ['nullable', 'numeric', 'min:0', 'max:999'],
            'notes'      => ['nullable', 'string', 'max:300'],
        ];
    }
}
