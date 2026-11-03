<?php

namespace App\Services\Territory;

use App\Enums\Territory\MembershipOriginType;
use App\Models\Territory\Municipality;
use App\Models\Territory\Region;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MunicipalityRegionMembershipService
{
    /**
     * Registra únicamente el punto de partida conocido, sin atribuirle una fecha oficial de incorporación.
     * Los municipios con historial previo se conservan sin cambios.
     */
    public function initialize(Municipality $municipality, string $validFrom): bool
    {
        Validator::make(['valid_from' => $validFrom], [
            'valid_from' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
        ])->validate();

        return DB::transaction(function () use ($municipality, $validFrom): bool {
            $municipality = Municipality::query()->lockForUpdate()->findOrFail($municipality->getKey());

            if ($municipality->regionMemberships()->exists()) {
                return false;
            }

            $municipality->regionMemberships()->create([
                'region_id' => $municipality->region_id,
                'valid_from' => $validFrom,
                'origin_type' => MembershipOriginType::BASELINE,
                'notes' => 'Punto de partida conocido del historial. No acredita la fecha oficial de incorporación ni pertenencias anteriores.',
            ]);

            return true;
        });
    }

    /** @param array{valid_from?: string|null, agreement_reference?: string|null, agreement_date?: string|null, notes?: string|null} $data */
    public function changeRegion(Municipality $municipality, int $regionId, array $data): Municipality
    {
        return DB::transaction(function () use ($municipality, $regionId, $data): Municipality {
            $municipality = Municipality::query()->lockForUpdate()->findOrFail($municipality->getKey());

            if ($municipality->region_id === $regionId) {
                return $municipality->load(['region', 'province']);
            }

            if (is_string($data['agreement_reference'] ?? null)) {
                $data['agreement_reference'] = Str::trim($data['agreement_reference']);
            }

            $data = Validator::make($data, [
                'valid_from' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
                'agreement_reference' => ['required', 'string', 'max:255'],
                'agreement_date' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
                'notes' => ['sometimes', 'nullable', 'string'],
            ])->validate();

            if (Region::query()->where('active', true)->sharedLock()->find($regionId) === null) {
                throw ValidationException::withMessages(['region_id' => 'La región seleccionada no está disponible.']);
            }

            $currentMembership = $municipality->regionMemberships()->whereNull('valid_to')->lockForUpdate()->first();

            if ($currentMembership === null || $currentMembership->region_id !== $municipality->region_id) {
                throw ValidationException::withMessages([
                    'region_id' => 'Inicialice o revise el historial regional antes de cambiar la región del municipio.',
                ]);
            }

            if ($data['valid_from'] <= $currentMembership->valid_from->toDateString()) {
                throw ValidationException::withMessages([
                    'valid_from' => 'La fecha efectiva debe ser posterior al inicio del periodo vigente. Las correcciones históricas requieren un procedimiento específico.',
                ]);
            }

            $currentMembership->update(['valid_to' => $data['valid_from']]);

            $municipality->regionMemberships()->create([
                ...$data,
                'region_id' => $regionId,
                'origin_type' => MembershipOriginType::AGREEMENT,
            ]);

            $municipality->update(['region_id' => $regionId]);

            return $municipality->refresh()->load(['region', 'province']);
        });
    }
}
