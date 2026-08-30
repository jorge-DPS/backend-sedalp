<?php

namespace App\Http\Requests\Communication;

use Illuminate\Foundation\Http\FormRequest;

class IndexNewsTrashRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('api')
            ?->can('news.trash.view') ?? false;
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
        if ($this->has('search')) {
            $search = trim(
                (string) $this->input('search')
            );

            $this->merge([
                'search' => $search !== ''
                    ? $search
                    : null,
            ]);
        }
    }
}