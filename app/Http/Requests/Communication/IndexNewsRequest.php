<?php

namespace App\Http\Requests\Communication;

use App\Enums\NewsStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexNewsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('api')?->can('news.view')
            ?? false;
    }

    public function rules(): array
    {
        return [
            'search' => [
                'nullable',
                'string',
                'max:150',
            ],

            'status' => [
                'nullable',
                Rule::enum(NewsStatus::class),
            ],

            'per_page' => [
                'nullable',
                'integer',
                'min:1',
                'max:100',
            ],
        ];
    }
}
