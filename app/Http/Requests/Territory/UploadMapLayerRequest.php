<?php

namespace App\Http\Requests\Territory;

use Illuminate\Foundation\Http\FormRequest;

class UploadMapLayerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user('api');
        if ($layer = $this->route('mapLayer')) {
            return ($user?->can('maps.update') ?? false)
                && ($layer->kind !== 'boundary' || $user->can('regions.update'));
        }

        return ($user?->can('maps.create') ?? false) || ($user?->can('maps.update') ?? false);
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'extensions:zip,json,geojson', 'max:'.config('maps.max_upload_kb')],
            'expected_version_id' => [$this->route('mapLayer') ? 'required' : 'sometimes', 'integer', 'min:1'],
            'replace_boundary' => ['sometimes', 'boolean'],
            'repair_geometries' => ['sometimes', 'boolean'],
        ];
    }
}
