<?php

namespace App\Http\Requests\Territory;

use App\Enums\Territory\MapCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PublishMapLayerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ($this->user('api')?->can('maps.publish') ?? false)
            && $this->user('api')->can('maps.view');
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'expected_version_id' => ['required', 'integer', 'min:1'],
            'expected_published_version_id' => ['present', 'nullable', 'integer', 'min:1'],
            'category' => [$this->isMethod('DELETE') ? 'prohibited' : 'required',
                Rule::enum(MapCategory::class)->only($this->route('mapLayer')->kind === 'boundary'
                    ? [MapCategory::BOUNDARIES]
                    : [MapCategory::ROADS, MapCategory::RIVERS, MapCategory::CABLEWAYS, MapCategory::OTHER])],
        ];
    }
}
