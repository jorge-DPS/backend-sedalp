<?php

namespace App\Http\Requests\Municipal;

use App\Enums\Auth\PermissionName;
use App\Enums\Municipal\PoaDocumentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexMunicipalPoaDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('api')?->can(PermissionName::MUNICIPAL_DOCUMENTS_VIEW->value) ?? false;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
            'document_type' => ['sometimes', Rule::enum(PoaDocumentType::class)],
            'is_current' => ['sometimes', 'boolean'],
            'published' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['is_current', 'published'] as $field) {
            if (is_string($this->input($field))) {
                $value = filter_var($this->input($field), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($value !== null) {
                    $this->merge([$field => $value]);
                }
            }
        }
    }
}
