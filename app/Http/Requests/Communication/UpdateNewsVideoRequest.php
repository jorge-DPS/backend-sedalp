<?php

namespace App\Http\Requests\Communication;

use App\Rules\YouTubeUrl;
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
                'bail',
                'sometimes',
                'required',
                'string',
                'max:2048',
                'url:http,https',
                new YouTubeUrl,
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
