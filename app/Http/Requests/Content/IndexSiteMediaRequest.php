<?php

namespace App\Http\Requests\Content;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexSiteMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('api')?->can('content.view') ?? false;
    }

    public function rules(): array
    {
        return [
            'profile' => ['sometimes', Rule::in(['desktop', 'mobile'])],
            'per_page' => ['sometimes', 'integer', 'between:1,50'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
