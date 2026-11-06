<?php

namespace App\Http\Requests\Investment;

use App\Enums\Auth\PermissionName;
use App\Models\Investment\Institution;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvestmentSourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('api')?->can(PermissionName::INVESTMENT_SOURCES_CREATE->value) ?? false;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'institution_id' => ['required', 'integer', Rule::exists(Institution::class, 'id')->withoutTrashed()->where('active', true)],
            'provided_by_name' => ['sometimes', 'nullable', 'string', 'max:200'],
            'source_type' => ['required', 'string', 'max:50'],
            'title' => ['required', 'string', 'max:255'],
            'fiscal_year' => ['sometimes', 'nullable', 'integer', 'between:1900,2100'],
            'document_reference' => ['sometimes', 'nullable', 'string', 'max:255'],
            'version_label' => ['sometimes', 'nullable', 'string', 'max:100'],
            'source_url' => ['sometimes', 'nullable', 'url:http,https'],
            'received_at' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'file_path' => ['prohibited'],
            'file' => ['prohibited'],
        ];
    }
}
