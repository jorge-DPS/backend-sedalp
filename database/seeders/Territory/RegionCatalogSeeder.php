<?php

namespace Database\Seeders\Territory;

use App\Models\Territory\Region;
use Illuminate\Database\Seeder;

class RegionCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $existing = Region::withTrashed()->get();

        foreach (array_keys(SimredTerritoryCatalog::regions()) as $name) {
            $shortName = substr($name, strlen('REGIÓN '));
            // METROPOLITANA es el código existente usado por RegionBoundarySeeder.
            $legacyCode = $shortName === 'METROPOLITANA' ? 'METROPOLITANA' : null;
            $region = SimredCatalogMatch::one($existing, [$name, $shortName], $legacyCode);

            if ($region === null) {
                $region = Region::create([
                    'name' => $name,
                    'code' => $legacyCode,
                    'active' => true,
                ]);
                $existing->push($region);
            } elseif ($region->name !== $name) {
                // Solo canoniza el nombre; conserva ID, código, estado e historial.
                $region->update(['name' => $name]);
            }
        }

        $this->command?->info('SIMRED: catálogo de 7 regiones procesado.');
    }
}
