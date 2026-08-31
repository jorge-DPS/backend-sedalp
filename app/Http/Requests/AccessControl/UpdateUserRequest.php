<?php

namespace App\Http\Requests\AccessControl;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('api')?->can('users.update') ?? false;
    }

    public function rules(): array
    {
        return [
            'email' => [
                'sometimes',
                'required',
                'string',
                'email:rfc',
                'max:255',

                Rule::unique('users', 'email')
                    ->ignore($this->route('user')),
            ],

            'password' => [
                'sometimes',
                'required',
                'confirmed',
                Password::min(12)
                    ->mixedCase()
                    ->numbers()
                    ->symbols(),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('email')) {
            $this->merge([
                'email' => strtolower(
                    trim((string) $this->input('email'))
                ),
            ]);
        }
    }
}
