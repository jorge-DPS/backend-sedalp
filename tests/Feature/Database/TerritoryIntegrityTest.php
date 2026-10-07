<?php

use App\Models\Territory\Municipality;
use App\Models\Territory\Province;
use App\Models\Territory\Region;
use App\Services\Territory\MunicipalityService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

it('la base de datos impide municipios huérfanos', function (string $foreignKey) {
    $data = Municipality::factory()->raw([$foreignKey => 999999]);

    expect(fn () => DB::transaction(fn () => Municipality::create($data)))
        ->toThrow(QueryException::class);
})->with(['region_id', 'province_id']);

it('la base de datos impide borrar físicamente los padres de un municipio', function (string $relation) {
    $municipality = Municipality::factory()->create();
    $parent = $municipality->{$relation};

    expect(fn () => DB::transaction(fn () => $parent->forceDelete()))
        ->toThrow(QueryException::class);
    expect($municipality->fresh())->not->toBeNull();
})->with(['region', 'province']);

it('la base de datos exige una región y una provincia', function (string $foreignKey) {
    $data = Municipality::factory()->raw([$foreignKey => null]);

    expect(fn () => DB::transaction(fn () => Municipality::create($data)))
        ->toThrow(QueryException::class);
})->with(['region_id', 'province_id']);

it('la base de datos impide códigos y nombres territoriales duplicados', function (string $model, string $field, string $value) {
    $model::factory()->create([$field => $value]);

    expect(fn () => DB::transaction(fn () => $model::factory()->create([$field => $value])))
        ->toThrow(QueryException::class);
})->with([
    [Region::class, 'code', 'REG-01'],
    [Province::class, 'code', 'PRO-01'],
    [Region::class, 'name', 'Región única'],
    [Province::class, 'name', 'Provincia única'],
    [Municipality::class, 'official_code', '00123'],
]);

it('la base de datos impide repetir un municipio en una provincia', function () {
    $municipality = Municipality::factory()->create();

    expect(fn () => DB::transaction(fn () => Municipality::factory()->create([
        'name' => $municipality->name,
        'province_id' => $municipality->province_id,
    ])))->toThrow(QueryException::class);
});

it('el servicio vuelve a comprobar padres eliminados después de la validación', function (string $relation) {
    $region = Region::factory()->create();
    $province = Province::factory()->create();
    $parent = $relation === 'region' ? $region : $province;
    $parent->delete();

    expect(fn () => app(MunicipalityService::class)->create([
        'name' => 'Municipio',
        'region_id' => $region->id,
        'province_id' => $province->id,
    ]))->toThrow(ValidationException::class);

    expect(Municipality::count())->toBe(0);
})->with(['region', 'province']);
