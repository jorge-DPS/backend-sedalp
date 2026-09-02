<?php

namespace App\Http\Requests\AccessControl;

use App\Enums\Auth\UserStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('api')
            ?->can('users.view') ?? false;
    }

    public function rules(): array
    {
        return [
            'search' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],

            'role' => [
                'sometimes',
                'nullable',
                'string',
                Rule::exists('roles', 'name')->where(
                    fn ($query) => $query->where(
                        'guard_name',
                        'api'
                    )
                ),
            ],

            'account_status' => [
                'sometimes',
                'nullable',
                Rule::enum(UserStatus::class),
            ],

            'staff_active' => [
                'sometimes',
                'nullable',
                'boolean',
            ],

            'organizational_unit_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('organizational_units', 'id'),
            ],

            'position_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('positions', 'id'),
            ],

            'profession_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('professions', 'id'),
            ],

            'per_page' => [
                'sometimes',
                'integer',
                'min:1',
                'max:100',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $search = $this->input('search');

        if ($this->has('search') && is_string($search)) {
            $search = trim($search);

            $this->merge([
                'search' => $search !== ''
                    ? $search
                    : null,
            ]);
        }

        $staffActive = $this->input('staff_active');

        if (is_string($staffActive)) {
            $normalized = match (strtolower($staffActive)) {
                'true' => true,
                'false' => false,
                default => $staffActive,
            };

            $this->merge([
                'staff_active' => $normalized,
            ]);
        }
    }
}
