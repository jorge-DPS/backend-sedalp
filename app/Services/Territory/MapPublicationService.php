<?php

namespace App\Services\Territory;

use App\Models\Territory\MapLayer;
use App\Models\Territory\Region;
use Illuminate\Support\Facades\DB;

class MapPublicationService
{
    /** @param array<string, mixed> $attributes */
    public function update(MapLayer $mapLayer, array $attributes, int $userId, bool $publish): MapLayer
    {
        return DB::transaction(function () use ($mapLayer, $attributes, $userId, $publish): MapLayer {
            $layer = MapLayer::query()->lockForUpdate()->findOrFail($mapLayer->id);
            $version = $layer->currentVersion()->firstOrFail();
            abort_unless($version->id === (int) $attributes['expected_version_id']
                && (int) $layer->published_version_id === (int) $attributes['expected_published_version_id'],
                409, 'La capa cambió. Cierre esta ventana y revise su estado antes de continuar.');

            if ($publish) {
                abort_unless($layer->region_id === null || Region::query()->whereKey($layer->region_id)->exists(),
                    422, 'La región asociada ya no está disponible.');
                abort_unless($version->features()->exists(), 422, 'La versión no contiene geometrías.');
                $layer->category = $attributes['category'];
                $layer->published_version_id = $version->id;
                $layer->published_at = now();
                $layer->published_by = $userId;
            } else {
                $layer->published_version_id = null;
                $layer->published_at = null;
                $layer->published_by = null;
            }
            $layer->save();

            return $layer->load(['region', 'currentVersion', 'publishedVersion']);
        });
    }
}
