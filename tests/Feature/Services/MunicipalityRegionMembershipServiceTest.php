<?php

use App\Enums\Territory\MembershipOriginType;
use App\Models\Territory\Municipality;
use App\Models\Territory\MunicipalityRegionMembership;
use App\Models\Territory\Province;
use App\Models\Territory\Region;
use App\Services\Territory\MunicipalityRegionMembershipService;
use App\Services\Territory\MunicipalityService;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-03 12:00:00'));
    $this->memberships = app(MunicipalityRegionMembershipService::class);
    $this->municipality = Municipality::factory()->create();
    $this->newRegion = Region::factory()->create();
    $this->agreement = [
        'valid_from' => '2026-06-01',
        'agreement_reference' => 'RES-TEST-001/2026',
        'agreement_date' => '2026-05-25',
        'notes' => 'Cambio regional acreditado para pruebas.',
    ];
});

it('inicializa el punto de partida sin inventar acuerdos ni modificar los datos del municipio', function (): void {
    $original = $this->municipality->fresh()->getAttributes();

    expect($this->memberships->initialize($this->municipality, '2020-01-01'))->toBeTrue();

    $baseline = $this->municipality->regionMemberships()->sole();
    expect($baseline->region_id)->toBe($this->municipality->region_id)
        ->and($baseline->origin_type)->toBe(MembershipOriginType::BASELINE)
        ->and($baseline->valid_from->toDateString())->toBe('2020-01-01')
        ->and($baseline->valid_to)->toBeNull()
        ->and($baseline->agreement_reference)->toBeNull()
        ->and($baseline->agreement_date)->toBeNull()
        ->and($this->municipality->fresh()->getAttributes())->toBe($original)
        ->and($this->municipality->regionMemberships()->validOn('2019-12-31')->exists())->toBeFalse();
});

it('crea el punto de partida de un municipio nuevo en la misma transacción', function (): void {
    $attributes = Municipality::factory()->raw();
    $municipality = app(MunicipalityService::class)->create($attributes);
    $membership = $municipality->regionMemberships()->sole();

    expect($membership->origin_type)->toBe(MembershipOriginType::BASELINE)
        ->and($membership->valid_from->toDateString())->toBe('2026-11-03')
        ->and($membership->region_id)->toBe($municipality->region_id)
        ->and($membership->agreement_reference)->toBeNull();
});

it('revierte la creación del municipio si no puede crear su punto de partida', function (): void {
    $attributes = Municipality::factory()->raw();
    $event = 'eloquent.creating: '.MunicipalityRegionMembership::class;
    Event::listen($event, function (): void {
        throw new RuntimeException('Fallo al registrar el punto de partida.');
    });

    try {
        expect(fn () => app(MunicipalityService::class)->create($attributes))->toThrow(RuntimeException::class);
    } finally {
        Event::forget($event);
    }

    expect(Municipality::query()->where('name', $attributes['name'])->exists())->toBeFalse();
});

it('registra un cambio cierra el periodo anterior y sincroniza la región sin modificar la provincia', function (): void {
    $this->memberships->initialize($this->municipality, '2020-01-01');
    $baseline = $this->municipality->regionMemberships()->sole();
    $originalProvince = $this->municipality->province_id;

    $municipality = $this->memberships->changeRegion($this->municipality, $this->newRegion->id, $this->agreement);
    $history = $municipality->regionMemberships()->orderBy('valid_from')->get();

    expect($history)->toHaveCount(2)
        ->and($baseline->fresh()->valid_to->toDateString())->toBe('2026-06-01')
        ->and($history[1]->origin_type)->toBe(MembershipOriginType::AGREEMENT)
        ->and($history[1]->valid_from->toDateString())->toBe('2026-06-01')
        ->and($history[1]->valid_to)->toBeNull()
        ->and($history[1]->agreement_reference)->toBe($this->agreement['agreement_reference'])
        ->and($history[1]->agreement_date->toDateString())->toBe($this->agreement['agreement_date'])
        ->and($history[1]->notes)->toBe($this->agreement['notes'])
        ->and($municipality->region->is($this->newRegion))->toBeTrue()
        ->and($municipality->province_id)->toBe($originalProvince);
});

it('admite un cambio efectivo hoy y acuerdos que acreditan un efecto retroactivo', function (): void {
    $this->memberships->initialize($this->municipality, '2020-01-01');

    $municipality = $this->memberships->changeRegion($this->municipality, $this->newRegion->id, [
        'valid_from' => '2026-11-03',
        'agreement_reference' => '  RES-TEST-HOY  ',
        'agreement_date' => '2026-11-03',
    ]);

    expect($municipality->region_id)->toBe($this->newRegion->id)
        ->and($municipality->regionMemberships()->whereNull('valid_to')->sole()->agreement_reference)->toBe('RES-TEST-HOY');

    $otherMunicipality = Municipality::factory()->create();
    $this->memberships->initialize($otherMunicipality, '2020-01-01');
    $this->memberships->changeRegion($otherMunicipality, $this->newRegion->id, [
        ...$this->agreement, 'agreement_date' => '2026-10-01',
    ]);

    expect($otherMunicipality->regionMemberships()->whereNull('valid_to')->sole()->agreement_date->toDateString())->toBe('2026-10-01');
});

it('permite registrar un acuerdo sin fecha documental conocida', function (): void {
    $this->memberships->initialize($this->municipality, '2020-01-01');

    $this->memberships->changeRegion($this->municipality, $this->newRegion->id, [
        'valid_from' => '2026-06-01',
        'agreement_reference' => 'RES-TEST-SIN-FECHA',
    ]);

    expect($this->municipality->regionMemberships()->whereNull('valid_to')->sole()->agreement_date)->toBeNull();
});

it('rechaza cambios sin metadatos válidos y conserva íntegro el periodo vigente', function (array $overrides): void {
    $this->memberships->initialize($this->municipality, '2020-01-01');
    $originalRegion = $this->municipality->region_id;

    expect(fn () => $this->memberships->changeRegion($this->municipality, $this->newRegion->id, [
        ...$this->agreement, ...$overrides,
    ]))->toThrow(ValidationException::class);

    expect($this->municipality->fresh()->region_id)->toBe($originalRegion)
        ->and($this->municipality->regionMemberships()->count())->toBe(1)
        ->and($this->municipality->regionMemberships()->sole()->valid_to)->toBeNull();
})->with([
    [['valid_from' => null]],
    [['valid_from' => '2026-11-04']],
    [['valid_from' => '2026-02-30']],
    [['valid_from' => '01/06/2026']],
    [['valid_from' => '2026-06-01 12:00:00']],
    [['valid_from' => '2019-01-01']],
    [['valid_from' => '2020-01-01']],
    [['agreement_reference' => null]],
    [['agreement_reference' => '   ']],
    [['agreement_reference' => str_repeat('x', 256)]],
    [['agreement_date' => '2026-11-04']],
    [['agreement_date' => 'incorrecta']],
    [['notes' => ['incorrecto']]],
]);

it('rechaza cambiar hacia regiones inexistentes inactivas o eliminadas', function (string $state): void {
    $this->memberships->initialize($this->municipality, '2020-01-01');
    $regionId = 999999999;

    if ($state === 'inactive') {
        $regionId = Region::factory()->inactive()->create()->id;
    } elseif ($state === 'deleted') {
        $region = Region::factory()->create();
        $region->delete();
        $regionId = $region->id;
    }

    expect(fn () => $this->memberships->changeRegion($this->municipality, $regionId, $this->agreement))
        ->toThrow(ValidationException::class);

    expect($this->municipality->regionMemberships()->sole()->valid_to)->toBeNull();
})->with(['missing', 'inactive', 'deleted']);

it('exige inicialización explícita y rechaza un historial desincronizado', function (bool $inconsistent): void {
    if ($inconsistent) {
        MunicipalityRegionMembership::factory()->for($this->municipality)->create(['region_id' => $this->newRegion->id]);
    }

    $originalRegion = $this->municipality->region_id;

    expect(fn () => $this->memberships->changeRegion($this->municipality, $this->newRegion->id, $this->agreement))
        ->toThrow(ValidationException::class);

    expect($this->municipality->fresh()->region_id)->toBe($originalRegion)
        ->and($this->municipality->regionMemberships()->count())->toBe($inconsistent ? 1 : 0);
})->with([false, true]);

it('revierte el cierre del periodo y cualquier inserción cuando falla el movimiento', function (string $stage): void {
    $this->memberships->initialize($this->municipality, '2020-01-01');
    $originalRegion = $this->municipality->region_id;
    $event = $stage === 'membership'
        ? 'eloquent.creating: '.MunicipalityRegionMembership::class
        : 'eloquent.updating: '.Municipality::class;

    Event::listen($event, function (): void {
        throw new RuntimeException('Fallo simulado en el movimiento.');
    });

    try {
        expect(fn () => $this->memberships->changeRegion($this->municipality, $this->newRegion->id, $this->agreement))
            ->toThrow(RuntimeException::class);
    } finally {
        Event::forget($event);
    }

    expect($this->municipality->fresh()->region_id)->toBe($originalRegion)
        ->and($this->municipality->regionMemberships()->count())->toBe(1)
        ->and($this->municipality->regionMemberships()->sole()->valid_to)->toBeNull();
})->with(['membership', 'municipality']);

it('revierte el movimiento si falla otro atributo dentro de MunicipalityService update', function (): void {
    $this->memberships->initialize($this->municipality, '2020-01-01');
    $originalRegion = $this->municipality->region_id;
    Municipality::factory()->for($this->municipality->province)->create(['name' => 'Duplicado']);

    expect(fn () => app(MunicipalityService::class)->update($this->municipality, [
        ...$this->agreement,
        'region_id' => $this->newRegion->id,
        'name' => 'Duplicado',
    ]))->toThrow(QueryException::class);

    expect($this->municipality->fresh()->region_id)->toBe($originalRegion)
        ->and($this->municipality->regionMemberships()->count())->toBe(1)
        ->and($this->municipality->regionMemberships()->sole()->valid_to)->toBeNull();
});

it('MunicipalityService no permite cambiar la región silenciosamente ni modificar la provincia durante el movimiento', function (bool $changeProvince): void {
    $this->memberships->initialize($this->municipality, '2020-01-01');
    $originalProvince = $this->municipality->province_id;
    $data = ['region_id' => $this->newRegion->id];

    if ($changeProvince) {
        $data = [...$data, ...$this->agreement, 'province_id' => Province::factory()->create()->id];
    }

    expect(fn () => app(MunicipalityService::class)->update($this->municipality, $data))
        ->toThrow(ValidationException::class);

    expect($this->municipality->fresh()->province_id)->toBe($originalProvince)
        ->and($this->municipality->regionMemberships()->count())->toBe(1);
})->with([false, true]);

it('no crea historial adicional al cambiar únicamente atributos o reenviar la misma región', function (bool $initialized): void {
    if ($initialized) {
        $this->memberships->initialize($this->municipality, '2020-01-01');
    }

    $before = $this->municipality->regionMemberships()->get()->toArray();

    app(MunicipalityService::class)->update($this->municipality, [
        'region_id' => (string) $this->municipality->region_id,
        'name' => 'Nombre corregido',
        'official_code' => '00123',
    ]);
    $this->memberships->changeRegion($this->municipality, $this->municipality->region_id, []);

    expect($this->municipality->fresh()->name)->toBe('Nombre corregido')
        ->and($this->municipality->regionMemberships()->get()->toArray())->toBe($before);
})->with([false, true]);

it('vuelve a leer la región bajo bloqueo y evita duplicar el movimiento con una instancia desactualizada', function (): void {
    $this->memberships->initialize($this->municipality, '2020-01-01');
    $staleMunicipality = $this->municipality->fresh();

    $this->memberships->changeRegion($this->municipality, $this->newRegion->id, $this->agreement);
    $this->memberships->changeRegion($staleMunicipality, $this->newRegion->id, []);

    expect($staleMunicipality->regionMemberships()->count())->toBe(2)
        ->and($staleMunicipality->fresh()->region_id)->toBe($this->newRegion->id);
});

it('consulta la región actual histórica y el listado regional respetando los extremos del intervalo', function (): void {
    $initialRegion = $this->municipality->region;
    $finalRegion = Region::factory()->create();
    $this->memberships->initialize($this->municipality, '2020-01-01');
    $this->memberships->changeRegion($this->municipality, $this->newRegion->id, [
        ...$this->agreement, 'valid_from' => '2021-01-01',
    ]);
    $this->memberships->changeRegion($this->municipality, $finalRegion->id, [
        ...$this->agreement, 'valid_from' => '2022-01-01',
    ]);

    foreach ([
        '2019-12-31' => null,
        '2020-01-01' => $initialRegion->id,
        '2020-12-31' => $initialRegion->id,
        '2021-01-01' => $this->newRegion->id,
        '2021-12-31' => $this->newRegion->id,
        '2022-01-01' => $finalRegion->id,
        '2026-11-03' => $finalRegion->id,
    ] as $date => $regionId) {
        expect($this->municipality->regionMemberships()->validOn($date)->first()?->region_id)->toBe($regionId);
    }

    expect($this->municipality->fresh()->region->is($finalRegion))->toBeTrue()
        ->and($this->newRegion->municipalityMemberships()->validOn('2021-01-01')->with('municipality')->sole()->municipality->is($this->municipality))->toBeTrue()
        ->and($this->newRegion->municipalityMemberships()->validOn('2022-01-01')->exists())->toBeFalse()
        ->and($this->municipality->regionMemberships()->orderBy('valid_from')->count())->toBe(3);
});

it('no reescribe periodos anteriores cuando se intenta introducir un cambio previo al periodo actual', function (): void {
    $this->memberships->initialize($this->municipality, '2020-01-01');
    $this->memberships->changeRegion($this->municipality, $this->newRegion->id, $this->agreement);
    $history = $this->municipality->regionMemberships()->orderBy('valid_from')->get()->toArray();
    $otherRegion = Region::factory()->create();

    expect(fn () => $this->memberships->changeRegion($this->municipality, $otherRegion->id, [
        ...$this->agreement, 'valid_from' => '2026-01-01',
    ]))->toThrow(ValidationException::class);

    expect($this->municipality->regionMemberships()->orderBy('valid_from')->get()->toArray())->toBe($history);
});

it('inicializa una sola vez incluso después de haber registrado cambios regionales', function (): void {
    $this->memberships->initialize($this->municipality, '2020-01-01');
    $this->memberships->changeRegion($this->municipality, $this->newRegion->id, $this->agreement);
    $history = $this->municipality->regionMemberships()->get()->toArray();

    expect($this->memberships->initialize($this->municipality, '2010-01-01'))->toBeFalse()
        ->and($this->municipality->regionMemberships()->get()->toArray())->toBe($history);
});

it('rechaza fechas de inicialización inválidas o futuras sin crear pertenencias', function (string $date): void {
    expect(fn () => $this->memberships->initialize($this->municipality, $date))->toThrow(ValidationException::class);
    expect($this->municipality->regionMemberships()->exists())->toBeFalse();
})->with(['2026-11-04', '2026-02-30', '01/01/2020', '']);
