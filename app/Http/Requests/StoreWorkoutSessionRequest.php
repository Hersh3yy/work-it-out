<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreWorkoutSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'logged_at'                        => ['required', 'date', 'before_or_equal:now'],
            'duration_minutes'                 => ['nullable', 'integer', 'min:1', 'max:600'],
            'perceived_exertion'               => ['nullable', 'integer', 'min:1', 'max:10'],
            'energy_level'                     => ['nullable', 'integer', 'min:1', 'max:5'],
            'notes'                            => ['nullable', 'string', 'max:500'],
            'completed_planned'                => ['boolean'],
            'exercises'                        => ['required', 'array', 'min:1'],
            'exercises.*.exercise_name'        => ['required', 'string', 'max:100'],
            'exercises.*.sets'                 => ['nullable', 'integer', 'min:1', 'max:100'],
            'exercises.*.reps'                 => ['nullable', 'integer', 'min:1', 'max:1000'],
            'exercises.*.weight_kg'            => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'exercises.*.duration_seconds'     => ['nullable', 'integer', 'min:1'],
            'exercises.*.distance_meters'      => ['nullable', 'integer', 'min:1'],
            'exercises.*.notes'                => ['nullable', 'string', 'max:200'],
        ];
    }
}
