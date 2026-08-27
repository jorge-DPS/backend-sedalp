<?php

namespace App\Http\Requests\People;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('api')
            ?->can('professions.update') ?? false;
    }

    public function rules(): array
    {
        $profession = $this->route('profession');

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:150',
                Rule::unique('professions', 'name')
                    ->ignore($profession),
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
