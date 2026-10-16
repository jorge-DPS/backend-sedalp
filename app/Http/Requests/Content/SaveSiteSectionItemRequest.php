<?php

namespace App\Http\Requests\Content;

use App\Models\Content\SiteSectionItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveSiteSectionItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('api')?->can('content.update') ?? false;
    }

    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';
        $unique = Rule::unique(SiteSectionItem::class, 'key')->where('site_section_id', $this->route('section')->id);
        if ($this->route('item') instanceof SiteSectionItem) {
            $unique->ignore($this->route('item'));
        }

        return [
            'key' => [$required, 'required', 'string', 'max:80', 'regex:/^[a-z][a-z0-9-]*$/', $unique],
            'title' => [$required, 'required', 'string', 'max:500'],
            'description' => [$required, 'required', 'string', 'max:10000'],
            'icon_key' => ['sometimes', 'nullable', Rule::in(['mission', 'vision', 'objective'])],
            'position' => ['sometimes', 'integer', 'between:0,32767'],
            'site_section_id' => ['prohibited'],
            'updated_by' => ['prohibited'],
        ];
    }
}
