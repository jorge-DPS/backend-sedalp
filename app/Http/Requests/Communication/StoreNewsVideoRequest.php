<?php

namespace App\Http\Requests\Communication;

use App\Rules\YouTubeUrl;
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
                'bail',
                'required',
                'string',
                'max:2048',
                'url:http,https',
                new YouTubeUrl,
            ],

            'title' => [
                'required',
                'string',
                'max:255',
            ],
        ];
    }
}
