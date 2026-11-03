<?php

namespace Database\Seeders\Territory;

use App\Models\Territory\Municipality;
use App\Models\Territory\Province;
use App\Models\Territory\Region;
use Illuminate\Database\Seeder;

use RuntimeException;

class MunicipalityCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $regions = Region::query()->get()->keyBy('name');
        $provinces = Province::query()->get()->keyBy('name');
        $existing = Municipality::withTrashed()->get();
        $total = 0;

        foreach (SimredTerritoryCatalog::regions() as $regionName => $provinceGroups) {
            $region = $regions->get($regionName);

            if ($region === null) {
                throw new RuntimeException("Primero ejecute RegionCatalogSeeder. Región ausente: {$regionName}");
            }

            foreach ($provinceGroups as $provinceName => $municipalities) {
                $province = $provinces->get($provinceName);

                if ($province === null) {
                    throw new RuntimeException("Primero ejecute ProvinceCatalogSeeder. Provincia ausente: {$provinceName}");
                }

                foreach ($municipalities as $name) {
                    $municipality = SimredCatalogMatch::one($existing, [$name]);

                    if ($municipality === null) {
                        // Se crea directamente porque MunicipalityService::create() fijaría
                        // «hoy» como inicio histórico, fecha no acreditada por el DOCX.
                        $municipality = Municipality::create([
                            'name' => $name,
                            'province_id' => $province->id,
                            'region_id' => $region->id,
                            'active' => true,
                        ]);
                        $existing->push($municipality);
                    } else {
                        if ((int) $municipality->province_id !== (int) $province->id
                            || (int) $municipality->region_id !== (int) $region->id) {
                            throw new RuntimeException(
                                "Asignación ya existente incompatible con el documento para {$name}. "
                                .'No se alteraron región, provincia ni historial.'
                            );
                        }

                        if ($municipality->regionMemberships()
                            ->whereNull('valid_to')
                            ->where('region_id', '!=', $region->id)
                            ->exists()) {
                            throw new RuntimeException("Historial regional desincronizado para {$name}.");
                        }

                        if ($municipality->name !== $name) {
                            $municipality->update(['name' => $name]);
                        }
                    }

                    $total++;
                }
            }
        }

        $this->command?->info("SIMRED: {$total} municipios procesados sin inventar fecha de incorporación.");
    }
}
