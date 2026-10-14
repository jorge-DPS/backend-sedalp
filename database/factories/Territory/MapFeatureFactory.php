<?php

namespace Database\Factories\Territory;

use App\Models\Territory\MapFeature;
use App\Models\Territory\MapLayerVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MapFeature> */
class MapFeatureFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['map_layer_version_id' => MapLayerVersion::factory(), 'position' => 1, 'properties' => ['name' => 'Estación'], 'geom' => 'SRID=4326;POINT(-68.15 -16.5)'];
    }
}
