<?php

namespace App\Services\Municipal;

use App\Models\Municipal\MunicipalTerm;
use App\Models\Territory\Municipality;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MunicipalTermService
{
    /** @param array<string, mixed> $data */
    public function save(Municipality $municipality, array $data, ?MunicipalTerm $term = null): MunicipalTerm
    {
        return DB::transaction(function () use ($municipality, $data, $term): MunicipalTerm {
            $municipality = Municipality::query()->where('active', true)->lockForUpdate()->findOrFail($municipality->id);
            if ($term !== null) {
                $term = $municipality->municipalTerms()->lockForUpdate()->findOrFail($term->id);
            }
            $values = Validator::make(array_replace($term?->only(['start_year', 'end_year']) ?? [], $data), [
                'start_year' => ['required', 'integer', 'between:1900,2100'],
                'end_year' => ['required', 'integer', 'between:1900,2100', 'gt:start_year',
                    Rule::unique(MunicipalTerm::class)->where('municipality_id', $municipality->id)
                        ->where('start_year', $data['start_year'] ?? $term?->start_year)->ignore($term?->id)],
            ])->validate();
            if ($term !== null && $term->municipalAuthorities()->exists()
                && ($term->start_year !== (int) $values['start_year'] || $term->end_year !== (int) $values['end_year'])) {
                throw ValidationException::withMessages(['term' => 'Una gestión con autoridades conserva su período histórico. Registre otra gestión o revise la asociación explícitamente.']);
            }
            if ($term === null) {
                return $municipality->municipalTerms()->create($values);
            }
            $term->update($values);

            return $term->refresh();
        }, 3);
    }
}
