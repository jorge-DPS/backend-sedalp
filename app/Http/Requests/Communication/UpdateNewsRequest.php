<?php

namespace App\Http\Requests\Communication;

use App\Enums\Communication\NewsStatus;;;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateNewsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('api')?->can('news.update')
            ?? false;
    }

    public function rules(): array
    {
        return [
            'title' => [
                'sometimes',
                'required',
                'string',
                'max:255',
            ],

            'subtitle' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],

            'excerpt' => [
                'sometimes',
                'required',
                'string',
            ],

            'description' => [
                'sometimes',
                'required',
                'string',
            ],

            'content' => [
                'sometimes',
                'required',
                'array',
            ],

            'content.type' => [
                'required_with:content',
                'string',
                'in:doc',
            ],

            'content.content' => [
                'nullable',
                'array',
            ],

            'status' => [
                'sometimes',
                Rule::enum(NewsStatus::class),
            ],

            'published_at' => [
                'sometimes',
                'nullable',
                'date',
                'required_if:status,published',
            ],
        ];
    }
}
