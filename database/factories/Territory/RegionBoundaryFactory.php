<?php

namespace Database\Factories\Territory;

use App\Models\Territory\Region;
use App\Models\Territory\RegionBoundary;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RegionBoundary> */
class RegionBoundaryFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'region_id' => Region::factory(),
            'geom' => 'SRID=4326;MULTIPOLYGON(((-68.2 -16.6,-68.1 -16.6,-68.1 -16.5,-68.2 -16.5,-68.2 -16.6)))',
            'source_filename' => 'test-boundary.shp',
            'source_srid' => 4326,
            'source_properties' => [],
        ];
    }
}
