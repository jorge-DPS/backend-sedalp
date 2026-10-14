<?php

namespace Database\Factories\Territory;

use App\Models\Territory\MapLayer;
use App\Models\Territory\MapLayerVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MapLayerVersion> */
class MapLayerVersionFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['map_layer_id' => MapLayer::factory(), 'number' => 1, 'uploaded_by' => User::factory(), 'source_filename' => 'points.geojson', 'source_path' => 'maps/originals/test.geojson', 'sha256' => hash('sha256', 'test'), 'summary' => ['feature_count' => 1, 'geometry_types' => ['Point' => 1], 'position_count' => 1, 'srid' => 4326]];
    }
}
