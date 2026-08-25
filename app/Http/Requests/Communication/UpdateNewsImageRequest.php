<?php

namespace App\Http\Requests\Communication;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNewsImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('api')?->can('news.update')
            ?? false;
    }

    public function rules(): array
    {
        return [
            'alt' => [
                'sometimes',
                'required',
                'string',
                'max:255',
            ],

            'caption' => [
                'sometimes',
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }
}
