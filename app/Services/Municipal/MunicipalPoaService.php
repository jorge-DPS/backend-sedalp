<?php

namespace App\Services\Municipal;

use App\Models\Municipal\MunicipalPoa;
use App\Models\Territory\Municipality;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class MunicipalPoaService
{
    /** @param array{fiscal_year: int|string, title?: string|null, notes?: string|null} $data */
    public function create(Municipality $municipality, array $data): MunicipalPoa
    {
        return DB::transaction(function () use ($municipality, $data): MunicipalPoa {
            $municipality = Municipality::query()->where('active', true)->lockForUpdate()->find($municipality->id);
            if ($municipality === null) {
                throw ValidationException::withMessages(['municipality_id' => 'El municipio no está disponible.']);
            }
            if ($municipality->municipalPoas()->where('fiscal_year', $data['fiscal_year'])->exists()) {
                throw ValidationException::withMessages(['fiscal_year' => 'Ya existe un POA para el municipio y la gestión.']);
            }

            $poa = $municipality->municipalPoas()->create($data);
            if (! $poa->exists) {
                throw new RuntimeException('No se pudo registrar el POA.');
            }

            return $poa->loadCount('documents');
        }, 3);
    }

    /** @param array{title?: string|null, notes?: string|null} $data */
    public function update(MunicipalPoa $poa, array $data): MunicipalPoa
    {
        return DB::transaction(function () use ($poa, $data): MunicipalPoa {
            if (Municipality::query()->where('active', true)->sharedLock()->find($poa->municipality_id) === null) {
                throw ValidationException::withMessages(['municipality_id' => 'El municipio no está disponible.']);
            }
            $poa = MunicipalPoa::query()->lockForUpdate()->findOrFail($poa->id);
            if (! $poa->update($data)) {
                throw new RuntimeException('No se pudo actualizar el POA.');
            }

            return $poa->refresh()->loadCount('documents');
        }, 3);
    }
}
