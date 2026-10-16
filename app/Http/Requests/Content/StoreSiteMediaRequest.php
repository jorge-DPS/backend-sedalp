<?php

namespace App\Http\Requests\Content;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSiteMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('api')?->can('content.media.create') ?? false;
    }

    public function rules(): array
    {
        $profile = in_array($this->input('profile'), ['desktop', 'mobile'], true) ? $this->input('profile') : 'desktop';
        $dimensions = config("content.images.$profile");

        return [
            'profile' => ['required', Rule::in(['desktop', 'mobile'])],
            'image' => ['bail', 'required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'extensions:jpg,jpeg,png,webp',
                'max:'.config('content.max_upload_kb'),
                Rule::dimensions()->minWidth($dimensions['width'])->minHeight($dimensions['height'])->maxWidth(8000)->maxHeight(8000)],
        ];
    }

    public function messages(): array
    {
        return ['image.dimensions' => 'La imagen debe cubrir las medidas del formato seleccionado y no superar 8000 píxeles por lado.'];
    }
}
