<?php

namespace App\Services\Municipal;

use App\Enums\Municipal\MunicipalAuthorityRole;
use App\Models\Municipal\MunicipalAuthority;
use App\Models\Municipal\MunicipalTerm;
use App\Models\Territory\Municipality;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class MunicipalAuthorityService
{
    /** @param array{full_name: string, role: string, position_title?: string|null, term_start?: string|null, term_end?: string|null, is_current?: bool, source_reference?: string|null, notes?: string|null} $data */
    public function create(Municipality $municipality, array $data): MunicipalAuthority
    {
        return DB::transaction(function () use ($municipality, $data): MunicipalAuthority {
            $municipality = $this->lockMunicipality($municipality->id);
            if (empty($data['municipal_term_id'])) {
                throw ValidationException::withMessages(['municipal_term_id' => 'Seleccione una gestión municipal válida.']);
            }
            $authority = $municipality->municipalAuthorities()->make($data);
            $this->validateState($authority);

            return $this->save($authority);
        }, 3);
    }

    /** @param array{full_name?: string, position_title?: string|null, term_start?: string|null, term_end?: string|null, is_current?: bool, source_reference?: string|null, notes?: string|null} $data */
    public function update(MunicipalAuthority $authority, array $data): MunicipalAuthority
    {
        return DB::transaction(function () use ($authority, $data): MunicipalAuthority {
            $this->lockMunicipality($authority->municipality_id);
            $authority = MunicipalAuthority::query()->lockForUpdate()->findOrFail($authority->id);
            $authority->fill($data);
            $this->validateState($authority);

            return $this->save($authority);
        }, 3);
    }

    public function publish(MunicipalAuthority $authority, bool $published): MunicipalAuthority
    {
        return DB::transaction(function () use ($authority, $published): MunicipalAuthority {
            $this->lockMunicipality($authority->municipality_id);
            $authority = MunicipalAuthority::query()->lockForUpdate()->findOrFail($authority->id);
            $authority->is_published = $published;

            return $this->save($authority);
        }, 3);
    }

    /** @param array{previous_authority_id: int|string, previous_term_end: string} $data */
    public function activateMayor(MunicipalAuthority $authority, array $data): MunicipalAuthority
    {
        return DB::transaction(function () use ($authority, $data): MunicipalAuthority {
            $this->lockMunicipality($authority->municipality_id);
            $authority = MunicipalAuthority::query()->lockForUpdate()->findOrFail($authority->id);
            if ($authority->role !== MunicipalAuthorityRole::MAYOR) {
                throw ValidationException::withMessages(['role' => 'La transición corresponde a alcaldes.']);
            }
            if ($authority->is_current) {
                return $authority;
            }

            $previous = MunicipalAuthority::query()->where('municipality_id', $authority->municipality_id)
                ->where('role', MunicipalAuthorityRole::MAYOR)->where('is_current', true)->lockForUpdate()->first();

            if ($previous === null || $previous->id !== (int) $data['previous_authority_id']) {
                throw ValidationException::withMessages(['previous_authority_id' => 'El alcalde vigente cambió. Revise el estado actual antes de continuar.']);
            }
            if ($authority->term_start === null || $data['previous_term_end'] >= $authority->term_start->toDateString()) {
                throw ValidationException::withMessages(['previous_term_end' => 'El cierre anterior debe preceder al inicio conocido del nuevo mandato.']);
            }

            $previous->fill(['term_end' => $data['previous_term_end'], 'is_current' => false]);
            $this->validateState($previous);
            $this->save($previous);

            $authority->is_current = true;
            $this->validateState($authority);

            return $this->save($authority);
        }, 3);
    }

    private function lockMunicipality(int $municipalityId): Municipality
    {
        $municipality = Municipality::query()->where('active', true)->lockForUpdate()->find($municipalityId);
        if ($municipality === null) {
            throw ValidationException::withMessages(['municipality_id' => 'El municipio no está disponible.']);
        }

        return $municipality;
    }

    private function validateState(MunicipalAuthority $authority): void
    {
        if ($authority->municipal_term_id !== null && ! MunicipalTerm::query()->where('municipality_id', $authority->municipality_id)
            ->whereKey($authority->municipal_term_id)->sharedLock()->exists()) {
            throw ValidationException::withMessages(['municipal_term_id' => 'La gestión debe pertenecer al mismo municipio.']);
        }
        if ($authority->term_start !== null && $authority->term_end !== null && $authority->term_end->lt($authority->term_start)) {
            throw ValidationException::withMessages(['term_end' => 'El fin del mandato no puede preceder a su inicio.']);
        }
        if ($authority->is_current && (($authority->term_start !== null && $authority->term_start->gt(today()))
            || ($authority->term_end !== null && $authority->term_end->lt(today())))) {
            throw ValidationException::withMessages(['is_current' => 'El período informado no corresponde a una autoridad actual.']);
        }
        if ($authority->is_current && $authority->role === MunicipalAuthorityRole::MAYOR
            && MunicipalAuthority::query()->where('municipality_id', $authority->municipality_id)
                ->where('role', MunicipalAuthorityRole::MAYOR)->where('is_current', true)
                ->when($authority->exists, fn (Builder $query): Builder => $query->whereKeyNot($authority->id))->exists()) {
            throw ValidationException::withMessages(['is_current' => 'Ya existe un alcalde actual. Utilice la transición explícita.']);
        }
    }

    private function save(MunicipalAuthority $authority): MunicipalAuthority
    {
        if (! $authority->save()) {
            throw new RuntimeException('No se pudo guardar la autoridad municipal.');
        }

        return $authority->refresh();
    }
}
