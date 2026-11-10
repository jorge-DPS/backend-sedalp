<?php

namespace App\Http\Requests\Municipal;

use App\Enums\Auth\PermissionName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMunicipalAuthorityPhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('api')?->can(PermissionName::MUNICIPAL_AUTHORITY_PHOTOS_UPDATE->value) ?? false;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'photo' => ['bail', 'required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'extensions:jpg,jpeg,png,webp',
                'max:'.config('municipal.photo_max_upload_kb'), Rule::dimensions()->maxWidth(8000)->maxHeight(8000)],
            'photo_path' => ['missing'],
            'municipality_id' => ['missing'],
        ];
    }
}
