<?php

namespace App\Http\Requests\AccessControl;

use Illuminate\Foundation\Http\FormRequest;

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
    }
}
