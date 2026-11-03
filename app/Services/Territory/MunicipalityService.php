<?php

namespace App\Services\Territory;

use App\Models\Territory\Municipality;
use App\Models\Territory\Province;
use App\Models\Territory\Region;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MunicipalityService
{
    public function __construct(private readonly MunicipalityRegionMembershipService $memberships) {}

    /** @param array{region_id: int|string, province_id: int|string, name: string, official_code?: string|null, active?: bool|int|string} $data */
    public function create(array $data): Municipality
    {
        return DB::transaction(function () use ($data): Municipality {
            $this->lockParents((int) $data['region_id'], (int) $data['province_id']);

            $municipality = Municipality::create($data);
            $this->memberships->initialize($municipality, today()->toDateString());

            return $municipality->load(['region', 'province']);
        });
    }

    /** @param array{region_id?: int|string, province_id?: int|string, name?: string, official_code?: string|null, active?: bool|int|string, valid_from?: string|null, agreement_reference?: string|null, agreement_date?: string|null, notes?: string|null} $data */
    public function update(Municipality $municipality, array $data): Municipality
    {
        return DB::transaction(function () use ($municipality, $data): Municipality {
            $municipality = Municipality::query()->lockForUpdate()->findOrFail($municipality->getKey());

            $this->lockParents(
                (int) ($data['region_id'] ?? $municipality->region_id),
                (int) ($data['province_id'] ?? $municipality->province_id),
            );

            $regionId = (int) ($data['region_id'] ?? $municipality->region_id);

            if ($regionId !== $municipality->region_id) {
                if (isset($data['province_id']) && (int) $data['province_id'] !== $municipality->province_id) {
                    throw ValidationException::withMessages(['province_id' => 'Un cambio regional debe conservar la provincia del municipio.']);
                }

                $municipality = $this->memberships->changeRegion($municipality, $regionId, Arr::only($data, [
                    'valid_from', 'agreement_reference', 'agreement_date', 'notes',
                ]));
            }

            $municipality->update(Arr::except($data, [
                'region_id', 'valid_from', 'agreement_reference', 'agreement_date', 'notes',
            ]));

            return $municipality->refresh()->load(['region', 'province']);
        });
    }

    /**
     * Coordinate assignments with catalog deletion so a municipality cannot
     * be assigned to a parent that was soft deleted after request validation.
     */
    private function lockParents(int $regionId, int $provinceId): void
    {
        if (Region::query()->sharedLock()->find($regionId) === null) {
            throw ValidationException::withMessages(['region_id' => 'La región seleccionada no está disponible.']);
        }

        if (Province::query()->sharedLock()->find($provinceId) === null) {
            throw ValidationException::withMessages(['province_id' => 'La provincia seleccionada no está disponible.']);
        }
    }
}
