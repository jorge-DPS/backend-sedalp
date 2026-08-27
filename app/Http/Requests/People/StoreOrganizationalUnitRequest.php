<?php

namespace App\Http\Requests\People;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrganizationalUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('api')
            ?->can('organizational_units.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:150',
                Rule::unique('organizational_units', 'name'),
            ],

            'code' => [
                'required',
                'string',
                'max:50',
                'regex:/^[A-Z][A-Z0-9_]*$/',
                Rule::unique('organizational_units', 'code'),
            ],

            'description' => [
                'nullable',
                'string',
                'max:255',
            ],

            'active' => [
                'sometimes',
                'boolean',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('name')) {
            $this->merge([
                'name' => trim((string) $this->input('name')),
            ]);
        }

        if ($this->has('code')) {
            $this->merge([
                'code' => strtoupper(
                    trim((string) $this->input('code'))
                ),
            ]);
        }
    }
}
