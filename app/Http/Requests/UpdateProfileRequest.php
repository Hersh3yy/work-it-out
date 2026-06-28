<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

final class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name'                 => ['sometimes', 'required', 'string', 'max:255'],
            'email'                => ['sometimes', 'required', 'string', 'email', 'max:255', "unique:users,email,{$this->user()->id}"],
            'current_password'     => ['required_with:password', 'current_password'],
            'password'             => ['nullable', 'confirmed', Password::defaults()],
        ];
    }
}
