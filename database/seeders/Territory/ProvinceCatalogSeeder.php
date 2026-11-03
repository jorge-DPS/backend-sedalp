<?php

namespace Database\Seeders\Territory;

use App\Models\Territory\Province;
use Illuminate\Database\Seeder;

class ProvinceCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $existing = Province::withTrashed()->get();

        foreach (SimredTerritoryCatalog::provinces() as $name) {
            $province = SimredCatalogMatch::one($existing, [$name]);

            if ($province === null) {
                $province = Province::create(['name' => $name, 'active' => true]);
                $existing->push($province);
            } elseif ($province->name !== $name) {
                // Conserva los códigos ya existentes (el DOCX no incluye códigos).
                $province->update(['name' => $name]);
            }
        }

        $this->command?->info('SIMRED: catálogo de 20 provincias procesado.');
    }
}
