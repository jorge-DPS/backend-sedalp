<?php

namespace Database\Seeders;

use App\Models\Territory\Region;
use Illuminate\Database\Seeder;

class RegionBoundarySeeder extends Seeder
{
    public function run(): void
    {
        $region = Region::firstOrCreate(
            ['code' => 'METROPOLITANA'],
            ['name' => 'Metropolitana', 'active' => true],
        );

        $this->command?->info('Región Metropolitana: ID '.$region->id.'. Importe su límite con territory:import-region-boundary.');
    }
}
