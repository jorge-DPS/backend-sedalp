<?php

namespace App\Http\Requests\Content;

use App\Models\Content\SiteMedia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSiteSectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('api')?->can('content.update') ?? false;
    }

    public function rules(): array
    {
        $hero = $this->route('section')?->type === 'hero';

        return [
            'key' => ['prohibited'],
            'type' => ['prohibited'],
            'site_page_id' => ['prohibited'],
            'updated_by' => ['prohibited'],
            'title' => ['sometimes', 'required', 'string', 'max:500'],
            'eyebrow' => ['sometimes', 'nullable', 'string', 'max:200'],
            'subtitle' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'description' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'image_alt' => ['sometimes', 'nullable', 'string', 'max:500'],
            'desktop_media_id' => [$hero ? 'sometimes' : 'prohibited', 'nullable', 'integer', Rule::exists(SiteMedia::class, 'id')->where('profile', 'desktop')],
            'mobile_media_id' => [$hero ? 'sometimes' : 'prohibited', 'nullable', 'integer', Rule::exists(SiteMedia::class, 'id')->where('profile', 'mobile')],
            'options' => ['sometimes', 'array:highlighted_text,button_label,button_to'],
            'options.highlighted_text' => ['sometimes', 'nullable', 'string', 'max:500'],
            'options.button_label' => ['sometimes', 'nullable', 'string', 'max:150'],
            'options.button_to' => ['sometimes', 'nullable', 'string', 'max:500', 'regex:~^(?:/(?!/)[a-zA-Z0-9/_?#=&%.\\-]*|#[a-zA-Z0-9_-]+)$~'],
        ];
    }
}
