<?php

namespace App\Http\Requests\People;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexStaffMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('api')
            ?->can('staff.view') ?? false;
    }

    public function rules(): array
    {
        return [
            'search' => [
                'sometimes',
                'nullable',
                'string',
                'max:254',
            ],

            'organizational_unit_id' => [
                'sometimes',
                'nullable',
                'integer',
                'min:1',
                Rule::exists('organizational_units', 'id')
                    ->whereNull('deleted_at'),
            ],

            'position_id' => [
                'sometimes',
                'nullable',
                'integer',
                'min:1',
                Rule::exists('positions', 'id')
                    ->whereNull('deleted_at'),
            ],

            'profession_id' => [
                'sometimes',
                'nullable',
                'integer',
                'min:1',
                Rule::exists('professions', 'id')
                    ->whereNull('deleted_at'),
            ],

            'active' => [
                'sometimes',
                'boolean',
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
        $data = [];

        if ($this->has('search')) {
            $search = trim(
                (string) $this->input('search')
            );

            $data['search'] = $search !== ''
                ? $search
                : null;
        }

        if ($this->has('active')) {
            $active = filter_var(
                $this->input('active'),
                FILTER_VALIDATE_BOOLEAN,
                FILTER_NULL_ON_FAILURE
            );

            if ($active !== null) {
                $data['active'] = $active;
            }
        }

        $this->merge($data);
    }
}
