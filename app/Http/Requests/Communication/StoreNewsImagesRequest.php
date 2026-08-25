<?php

namespace App\Http\Requests\Communication;

use Illuminate\Foundation\Http\FormRequest;

class StoreNewsImagesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('api')?->can('news.update')
            ?? false;
    }

    public function rules(): array
    {
        return [
            'images' => [
                'required',
                'array',
                'min:1',
                'max:20',
            ],

            'images.*.file' => [
                'required',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],

            'images.*.alt' => [
                'required',
                'string',
                'max:255',
            ],

            'images.*.caption' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }
}
