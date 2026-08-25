<?php

namespace App\Http\Requests\Communication;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNewsVideoRequest extends FormRequest
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
                'sometimes',
                'required',
                'url',
                'max:2048',
            ],

            'title' => [
                'sometimes',
                'required',
                'string',
                'max:255',
            ],
        ];
    }
}
