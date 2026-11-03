<?php

namespace Database\Seeders\Territory;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/** Entrada única recomendada: catálogo completo dentro de una transacción. */
class TerritoryCatalogSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $this->call([
                RegionCatalogSeeder::class,
                ProvinceCatalogSeeder::class,
                MunicipalityCatalogSeeder::class,
            ]);
        });

        $this->command?->info('SIMRED: catálogo territorial cargado. Inicialice el historial con fecha validada aparte.');
    }
}
