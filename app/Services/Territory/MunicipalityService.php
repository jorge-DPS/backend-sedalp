<?php

namespace App\Services\Territory;

use App\Models\Territory\Municipality;
use App\Models\Territory\Province;
use App\Models\Territory\Region;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MunicipalityService
{
    /** @param array{region_id: int|string, province_id: int|string, name: string, official_code?: string|null, active?: bool|int|string} $data */
    public function create(array $data): Municipality
    {
        return DB::transaction(function () use ($data): Municipality {
            $this->lockParents((int) $data['region_id'], (int) $data['province_id']);

            return Municipality::create($data)->load(['region', 'province']);
        });
    }

    /** @param array{region_id?: int|string, province_id?: int|string, name?: string, official_code?: string|null, active?: bool|int|string} $data */
    public function update(Municipality $municipality, array $data): Municipality
    {
        return DB::transaction(function () use ($municipality, $data): Municipality {
            $municipality = Municipality::query()->lockForUpdate()->findOrFail($municipality->getKey());

            $this->lockParents(
                (int) ($data['region_id'] ?? $municipality->region_id),
                (int) ($data['province_id'] ?? $municipality->province_id),
            );

            $municipality->update($data);

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
