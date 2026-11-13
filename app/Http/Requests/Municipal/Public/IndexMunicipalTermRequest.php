<?php

namespace App\Http\Requests\Municipal\Public;

class IndexMunicipalTermRequest extends \App\Http\Requests\Municipal\IndexMunicipalTermRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [...parent::rules(), 'per_page' => ['sometimes', 'integer', 'between:1,50']];
    }
}
