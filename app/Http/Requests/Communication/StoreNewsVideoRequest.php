<?php

namespace App\Http\Requests\Communication;

use Illuminate\Foundation\Http\FormRequest;

class StoreNewsVideoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('api')?->can('news.update')
            ?? false;
    }

    public function rules(): array
    {
        return [
            'youtube_url' => [
                'required',
                'url',
                'max:2048',
            ],

            'title' => [
                'required',
                'string',
                'max:255',
            ],
        ];
    }
}
