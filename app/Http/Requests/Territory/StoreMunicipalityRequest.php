<?php

namespace App\Http\Requests\Territory;

use App\Models\Territory\Municipality;
use App\Models\Territory\Province;
use App\Models\Territory\Region;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreMunicipalityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('api')?->can('municipalities.create') ?? false;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'region_id' => ['required', 'integer', Rule::exists(Region::class, 'id')->withoutTrashed()],
            'province_id' => ['required', 'integer', Rule::exists(Province::class, 'id')->withoutTrashed()],
            'name' => ['required', 'string', 'max:150'],
            'official_code' => ['sometimes', 'nullable', 'string', 'max:20', Rule::unique(Municipality::class, 'official_code')],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['name', 'province_id'])) {
                    return;
                }

                $exists = Municipality::withTrashed()
                    ->where('name', $this->input('name'))
                    ->where('province_id', $this->input('province_id'))
                    ->exists();

                if ($exists) {
                    $validator->errors()->add('name', 'Ya existe un municipio con este nombre en la provincia.');
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['name', 'official_code'] as $field) {
            $value = $this->input($field);

            if (is_string($value)) {
                $value = trim($value);

                $this->merge([$field => $value === '' ? null : $value]);
            }
        }
    }
}
