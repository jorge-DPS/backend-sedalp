<?php

namespace App\Http\Requests\AccessControl;

use Illuminate\Foundation\Http\FormRequest;

class DeleteUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('api')
            ?->can('users.delete') ?? false;
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
