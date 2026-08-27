<?php

namespace App\Http\Requests\People;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePositionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('api')
            ?->can('positions.update') ?? false;
    }

    public function rules(): array
    {
        $position = $this->route('position');

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:150',
                Rule::unique('positions', 'name')
                    ->ignore($position),
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
        if ($this->has('name')) {
            $this->merge([
                'name' => trim((string) $this->input('name')),
            ]);
        }
    }
}
