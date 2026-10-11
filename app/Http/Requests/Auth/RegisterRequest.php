<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Enums\TrainerPersona;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

final class RegisterRequest extends FormRequest
{
    /**
     * Closed in production: people join through a Telegram invite link. Checked
     * before validation, so a closed endpoint never reveals which emails exist.
     */
    public function authorize(): bool
    {
        return (bool) config('auth.registration_open');
    }

    protected function failedAuthorization(): never
    {
        throw new AuthorizationException('Registration is closed. Ask for an invite.');
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
