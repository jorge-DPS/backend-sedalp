<?php

namespace App\Http\Requests\People;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrganizationalUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('api')
            ?->can('organizational_units.update') ?? false;
    }

    public function rules(): array
    {
        $unit = $this->route('organizationalUnit');

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:150',
                Rule::unique('organizational_units', 'name')
                    ->ignore($unit),
            ],

            'code' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                'regex:/^[A-Z][A-Z0-9_]*$/',
                Rule::unique('organizational_units', 'code')
                    ->ignore($unit),
            ],

            'description' => [
                'sometimes',
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
        $data = [];

        if ($this->has('name')) {
            $data['name'] = trim(
                (string) $this->input('name')
            );
        }

        if ($this->has('code')) {
            $data['code'] = strtoupper(
                trim((string) $this->input('code'))
            );
        }

        $this->merge($data);
    }
}
