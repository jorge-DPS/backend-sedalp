<?php

namespace App\Http\Requests\Territory;

use Illuminate\Validation\Rule;

class StoreMapLayerRequest extends UploadMapLayerRequest
{
    public function authorize(): bool
    {
        return ($this->user('api')?->can('maps.create') ?? false)
            && ($this->input('kind') !== 'boundary' || $this->user('api')->can('regions.update'));
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return parent::rules() + [
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'kind' => ['required', Rule::in(['boundary', 'overlay'])],
            'region_id' => ['required_if:kind,boundary', 'nullable', 'integer', Rule::exists('regions', 'id')->whereNull('deleted_at')],
        ];
    }
}
