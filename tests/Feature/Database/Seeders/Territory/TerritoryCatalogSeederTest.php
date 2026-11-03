<?php

use App\Models\Territory\Municipality;
use App\Models\Territory\Province;
use App\Models\Territory\Region;
use Database\Seeders\RegionBoundarySeeder;
// use Database\Seeders\SimredTerritoryCatalog;
use Database\Seeders\Territory\SimredTerritoryCatalog;
// use Database\Seeders\TerritoryCatalogSeeder;
use Database\Seeders\Territory\TerritoryCatalogSeeder;
use Illuminate\Console\Command;

it('carga exactamente las 7 regiones, 20 provincias y 87 municipios del DOCX', function (): void {
    $this->seed(TerritoryCatalogSeeder::class);

    expect(Region::count())->toBe(7)
        ->and(Province::count())->toBe(20)
        ->and(Municipality::count())->toBe(87);

    foreach (SimredTerritoryCatalog::regions() as $regionName => $provinceGroups) {
        $region = Region::where('name', $regionName)->sole();
        $expectedCount = array_sum(array_map(count(...), array_values($provinceGroups)));
        expect($region->municipalities()->count())->toBe($expectedCount);

        foreach ($provinceGroups as $provinceName => $names) {
            $province = Province::where('name', $provinceName)->sole();

            foreach ($names as $name) {
                $municipality = Municipality::where('name', $name)->sole();
                expect($municipality->region_id)->toBe($region->id)
                    ->and($municipality->province_id)->toBe($province->id)
                    ->and($municipality->official_code)->toBeNull();
            }
        }
    }

    expect(Municipality::where('name', 'Copacabana')->sole()->province->name)->toBe('LOS ANDES')
        ->and(Municipality::where('name', 'Puerto Peréz')->exists())->toBeTrue()
        ->and(Municipality::where('name', 'Puerto Guaqui')->exists())->toBeTrue();
});

it('es idempotente y no crea periodos históricos con la fecha de ejecución', function (): void {
    $this->seed(TerritoryCatalogSeeder::class);
    $ids = Municipality::orderBy('name')->pluck('id', 'name')->all();

    $this->seed(TerritoryCatalogSeeder::class);

    expect(Region::count())->toBe(7)
        ->and(Province::count())->toBe(20)
        ->and(Municipality::count())->toBe(87)
        ->and(Municipality::orderBy('name')->pluck('id', 'name')->all())->toBe($ids)
        ->and(\App\Models\Territory\MunicipalityRegionMembership::count())->toBe(0);
});

it('reutiliza región Metropolitana y provincias en formato título sin duplicarlas', function (): void {
    $this->seed(RegionBoundarySeeder::class);
    $legacy = Province::create(['name' => 'Muñecas', 'code' => '02-05']);
    $oldRegion = Region::where('code', 'METROPOLITANA')->sole();

    $this->seed(TerritoryCatalogSeeder::class);

    expect(Region::count())->toBe(7)
        ->and(Region::where('name', 'REGIÓN METROPOLITANA')->sole()->id)->toBe($oldRegion->id)
        ->and(Province::count())->toBe(20)
        ->and(Province::where('name', 'MUÑECAS')->sole()->id)->toBe($legacy->id)
        ->and($legacy->fresh()->code)->toBe('02-05');
});

it('rechaza conflictos sin reescribir datos ni dejar una carga parcial', function (): void {
    $unexpectedRegion = Region::create(['name' => 'Región de prueba']);
    $unexpectedProvince = Province::create(['name' => 'Provincia de prueba']);
    $municipality = Municipality::create([
        'name' => 'Viacha',
        'region_id' => $unexpectedRegion->id,
        'province_id' => $unexpectedProvince->id,
    ]);

    expect(fn () => $this->seed(TerritoryCatalogSeeder::class))
        ->toThrow(RuntimeException::class, 'Viacha');

    expect(Municipality::count())->toBe(1)
        ->and(Municipality::findOrFail($municipality->id)->region_id)->toBe($unexpectedRegion->id)
        ->and(Region::count())->toBe(1)
        ->and(Province::count())->toBe(1);
});

it('registra una sola pertenencia inicial por municipio cuando se decide la fecha', function (): void {
    $this->seed(TerritoryCatalogSeeder::class);

    $this->artisan('territory:initialize-region-memberships', ['--from' => '2020-01-01'])
        ->assertExitCode(Command::SUCCESS);
    $this->artisan('territory:initialize-region-memberships', ['--from' => '2020-01-01'])
        ->assertExitCode(Command::SUCCESS);

    expect(\App\Models\Territory\MunicipalityRegionMembership::count())->toBe(87)
        ->and(\App\Models\Territory\MunicipalityRegionMembership::whereNull('valid_to')->count())->toBe(87)
        ->and(\App\Models\Territory\MunicipalityRegionMembership::where('origin_type', 'baseline')->count())->toBe(87);
});

it('no recrea un municipio eliminado lógicamente sin revisión', function (): void {
    $this->seed(TerritoryCatalogSeeder::class);
    Municipality::where('name', 'Palca')->sole()->delete();

    expect(fn () => $this->seed(TerritoryCatalogSeeder::class))
        ->toThrow(RuntimeException::class, 'Palca');

    expect(Municipality::withTrashed()->count())->toBe(87)
        ->and(Municipality::count())->toBe(86);
});
