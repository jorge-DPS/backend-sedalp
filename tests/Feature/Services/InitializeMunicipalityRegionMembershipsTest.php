<?php

use App\Enums\Territory\MembershipOriginType;
use App\Models\Territory\Municipality;
use App\Services\Territory\MunicipalityRegionMembershipService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-03 12:00:00'));
});

it('inicializa municipios existentes de forma idempotente sin alterar región ni provincia', function (): void {
    $municipalities = Municipality::factory()->count(2)->create();
    $original = $municipalities->mapWithKeys(fn (Municipality $municipality): array => [$municipality->id => $municipality->fresh()->getAttributes()]);

    foreach (['2020-01-01', '2010-01-01'] as $date) {
        $this->artisan('territory:initialize-region-memberships', ['--from' => $date])
            ->assertExitCode(Command::SUCCESS);
    }

    foreach ($municipalities as $municipality) {
        $baseline = $municipality->regionMemberships()->sole();
        expect($baseline->origin_type)->toBe(MembershipOriginType::BASELINE)
            ->and($baseline->valid_from->toDateString())->toBe('2020-01-01')
            ->and($baseline->agreement_reference)->toBeNull()
            ->and($municipality->fresh()->getAttributes())->toBe($original[$municipality->id]);
    }
});

it('permite inicializar únicamente los municipios seleccionados', function (): void {
    $selected = Municipality::factory()->create();
    $other = Municipality::factory()->create();

    $this->artisan('territory:initialize-region-memberships', [
        '--from' => '2020-01-01',
        '--municipality' => [(string) $selected->id],
    ])->assertExitCode(Command::SUCCESS);

    expect($selected->regionMemberships()->count())->toBe(1)
        ->and($other->regionMemberships()->exists())->toBeFalse();
});

it('no modifica el historial ya existente al ejecutar el comando', function (): void {
    $municipality = Municipality::factory()->create();
    app(MunicipalityRegionMembershipService::class)->initialize($municipality, '2018-01-01');
    $history = $municipality->regionMemberships()->get()->toArray();

    $this->artisan('territory:initialize-region-memberships', ['--from' => '2020-01-01'])
        ->assertExitCode(Command::SUCCESS);

    expect($municipality->regionMemberships()->get()->toArray())->toBe($history);
});

it('inicializa municipios inactivos pero excluye municipios eliminados', function (): void {
    $inactive = Municipality::factory()->inactive()->create();
    $deleted = Municipality::factory()->create();
    $deleted->delete();

    $this->artisan('territory:initialize-region-memberships', ['--from' => '2020-01-01'])
        ->assertExitCode(Command::SUCCESS);

    expect($inactive->regionMemberships()->count())->toBe(1)
        ->and($deleted->regionMemberships()->exists())->toBeFalse()
        ->and($inactive->fresh()->active)->toBeFalse();
});

it('exige una fecha explícita válida y no futura para inicializar', function (?string $date): void {
    $municipality = Municipality::factory()->create();

    $this->artisan('territory:initialize-region-memberships', ['--from' => $date])
        ->assertExitCode(Command::FAILURE);

    expect($municipality->regionMemberships()->exists())->toBeFalse();
})->with([null, '', '2026-11-04', '2026-02-30', '01/01/2020']);

it('rechaza una selección inválida antes de inicializar ningún municipio', function (string $invalidId): void {
    $municipality = Municipality::factory()->create();

    $this->artisan('territory:initialize-region-memberships', [
        '--from' => '2020-01-01',
        '--municipality' => [(string) $municipality->id, $invalidId],
    ])->assertExitCode(Command::FAILURE);

    expect($municipality->regionMemberships()->exists())->toBeFalse();
})->with(['999999999', 'incorrecto', '0']);
