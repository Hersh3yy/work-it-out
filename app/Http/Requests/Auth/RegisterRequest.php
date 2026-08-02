<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Enums\TrainerPersona;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

final class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'trainer_persona' => ['sometimes', Rule::enum(TrainerPersona::class)],
            'device_name' => ['sometimes', 'string', 'max:255'],
        ];
    }
}
