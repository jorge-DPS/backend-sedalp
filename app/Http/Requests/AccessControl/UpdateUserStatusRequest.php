<?php

namespace App\Http\Requests\AccessControl;

use App\Enums\Auth\UserStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('api')
            ?->can('users.status.update') ?? false;
    }

    public function rules(): array
    {
        return [
            'status' => [
                'required',
                Rule::enum(UserStatus::class),
            ],
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
