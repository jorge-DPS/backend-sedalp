<?php

namespace App\Http\Requests\Communication;

use App\Enums\Communication\NewsStatus;;;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreNewsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('api')?->can('news.create')
            ?? false;
    }

    public function rules(): array
    {
        return [
            'title' => [
                'required',
                'string',
                'max:255',
            ],

            'subtitle' => [
                'nullable',
                'string',
                'max:255',
            ],

            'excerpt' => [
                'required',
                'string',
            ],

            'description' => [
                'required',
                'string',
            ],

            'content' => [
                'required',
                'array',
            ],

            'content.type' => [
                'required',
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
                'nullable',
                'date',
                'required_if:status,published',
            ],
        ];
    }
}
