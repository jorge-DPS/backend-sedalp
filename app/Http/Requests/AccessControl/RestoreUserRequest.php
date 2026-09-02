<?php

namespace App\Http\Requests\AccessControl;

use Illuminate\Foundation\Http\FormRequest;

class RestoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user('api');

        return $user !== null
            && $user->hasRole('super_admin')
            && $user->can('users.restore');
    }

    public function rules(): array
    {
        return [
            'reason' => [
                'required',
                'string',
                'max:1000',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('reason')) {
            $this->merge([
                'reason' => trim((string) $this->input('reason')),
            ]);
        }
    }
}
