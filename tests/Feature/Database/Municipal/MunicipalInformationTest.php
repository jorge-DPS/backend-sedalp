<?php

use App\Enums\Municipal\MunicipalAuthorityRole;
use App\Enums\Municipal\PoaDocumentType;
use App\Models\Municipal\MunicipalAuthority;
use App\Models\Municipal\MunicipalPoa;
use App\Models\Municipal\MunicipalPoaDocument;
use App\Models\Municipal\MunicipalPoaVersion;
use App\Models\Territory\Municipality;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    expect(DB::connection()->getDriverName())->toBe('pgsql');
});

it('mantiene POA de distintas gestiones con sus documentos iniciales y reformulados', function (): void {
    $municipality = Municipality::factory()->create();
    $poa = MunicipalPoa::factory()->for($municipality)->create(['fiscal_year' => 2026]);
    $next = MunicipalPoa::factory()->for($municipality)->create(['fiscal_year' => 2027]);
    $initial = MunicipalPoaDocument::factory()->forPoa($poa)->create();
    $reformulated = MunicipalPoaDocument::factory()->forPoa($poa, 2)->published()->create();

    expect($municipality->municipalPoas()->count())->toBe(2)
        ->and($poa->municipality->is($municipality))->toBeTrue()
        ->and($poa->documents()->count())->toBe(2)
        ->and($initial->municipalPoa->is($poa))->toBeTrue()
        ->and($next->documents()->exists())->toBeFalse()
        ->and($reformulated->document_type)->toBe(PoaDocumentType::REFORMULATED)
        ->and($initial->file_size_bytes)->toBeNull()
        ->and($initial->received_at)->toBeNull();
});

it('impide duplicar el POA de un municipio y gestión', function (): void {
    $poa = MunicipalPoa::factory()->create();

    expect(fn () => DB::transaction(fn () => MunicipalPoa::factory()->create([
        'municipality_id' => $poa->municipality_id, 'fiscal_year' => $poa->fiscal_year,
    ])))->toThrow(QueryException::class);
});

it('impide números de versión repetidos y dos documentos actuales', function (bool $duplicateVersion): void {
    $initial = MunicipalPoaDocument::factory()->current()->create();

    expect(fn () => DB::transaction(fn () => MunicipalPoaDocument::factory()->forPoa($initial->municipalPoa, $duplicateVersion ? 1 : 2)->current()->create()))->toThrow(QueryException::class);
})->with([false, true]);

it('rechaza versiones tipos tamaños MIME y publicación incoherentes', function (array $changes): void {
    $isVersion = array_intersect(array_keys($changes), ['version_number', 'document_type', 'published_at', 'is_current']) !== [];
    $attributes = $isVersion ? MunicipalPoaVersion::factory()->make()->getAttributes() : MunicipalPoaDocument::factory()->make()->getAttributes();
    $table = $isVersion ? 'municipal_poa_versions' : 'municipal_poa_documents';

    expect(fn () => DB::transaction(fn () => DB::table($table)->insert([...$attributes, ...$changes])))
        ->toThrow(QueryException::class);
})->with([
    [['version_number' => 0]],
    [['version_number' => 2, 'document_type' => 'initial']],
    [['version_number' => 1, 'document_type' => 'reformulated']],
    [['document_type' => 'other']],
    [['file_size_bytes' => -1]],
    [['mime_type' => 'text/html']],
    [['is_current' => true, 'published_at' => null]],
    [['pdf_path' => 'https://example.com/private.pdf']],
    [['pdf_path' => 'municipal/poas/../private.pdf']],
]);

it('solo considera disponibles los PDFs publicados y oculta su ruta privada al serializar', function (): void {
    $draft = MunicipalPoaDocument::factory()->create();
    $published = MunicipalPoaDocument::factory()->published()->create();
    $future = MunicipalPoaDocument::factory()->create();
    $future->version->update(['published_at' => now()->addDay()]);

    expect($draft->isPubliclyAvailable())->toBeFalse()
        ->and($future->isPubliclyAvailable())->toBeFalse()
        ->and($published->isPubliclyAvailable())->toBeTrue()
        ->and(MunicipalPoaDocument::publiclyAvailable()->sole()->is($published))->toBeTrue()
        ->and($published->toArray())->not->toHaveKey('pdf_path');
});

it('preserva los documentos históricos frente a cambios de archivo o eliminación', function (bool $deleting): void {
    $document = MunicipalPoaDocument::factory()->create();

    expect(fn () => DB::transaction(fn () => $deleting
        ? $document->delete()
        : $document->update(['pdf_path' => 'municipal/poas/replacement.pdf'])))
        ->toThrow(QueryException::class);

    $this->assertModelExists($document);
})->with([false, true]);

it('permite un alcalde y varios concejales actuales y conserva a las autoridades anteriores', function (): void {
    $municipality = Municipality::factory()->create();
    $former = MunicipalAuthority::factory()->mayor()->for($municipality)->create();
    $mayor = MunicipalAuthority::factory()->mayor()->current()->published()->for($municipality)->create();
    MunicipalAuthority::factory()->current()->for($municipality)->count(3)->create();

    expect($municipality->municipalAuthorities()->count())->toBe(5)
        ->and($mayor->municipality->is($municipality))->toBeTrue()
        ->and($mayor->role)->toBe(MunicipalAuthorityRole::MAYOR)
        ->and($former->term_start)->toBeNull()
        ->and($former->term_end)->toBeNull()
        ->and(MunicipalAuthority::published()->sole()->is($mayor))->toBeTrue();

    expect(fn () => DB::transaction(fn () => MunicipalAuthority::factory()->mayor()->current()->for($municipality)->create()))
        ->toThrow(QueryException::class);
});

it('rechaza cargos y periodos de autoridad incoherentes', function (array $changes): void {
    $attributes = MunicipalAuthority::factory()->make()->getAttributes();

    expect(fn () => DB::transaction(fn () => DB::table('municipal_authorities')->insert([...$attributes, ...$changes])))
        ->toThrow(QueryException::class);
})->with([
    [['role' => 'other']],
    [['term_start' => '2026-01-01', 'term_end' => '2025-12-31']],
    [['term_start' => '2026-02-30']],
]);

it('exige años coherentes para los POA', function (int $year): void {
    expect(fn () => DB::transaction(fn () => MunicipalPoa::factory()->create(['fiscal_year' => $year])))
        ->toThrow(QueryException::class);
})->with([0, 1899, 2101]);

it('protege las claves foráneas municipales y la eliminación de padres', function (string $model, string $foreignKey, string $parentRelation): void {
    $record = $model::factory()->create();
    $parent = $record->{$parentRelation};

    expect(fn () => DB::transaction(fn () => $record->update([$foreignKey => 999999999])))
        ->toThrow(QueryException::class);

    expect(fn () => DB::transaction(fn () => method_exists($parent, 'forceDelete') ? $parent->forceDelete() : $parent->delete()))
        ->toThrow(QueryException::class);
})->with([
    [MunicipalPoa::class, 'municipality_id', 'municipality'],
    [MunicipalPoaDocument::class, 'municipal_poa_version_id', 'version'],
    [MunicipalAuthority::class, 'municipality_id', 'municipality'],
]);

it('revierte las tablas municipales y las recrea sin alterar los municipios', function (): void {
    $municipality = Municipality::factory()->create();
    $tables = ['municipal_poas', 'municipal_poa_documents', 'municipal_authorities'];
    $migrations = [];

    foreach ($tables as $table) {
        $paths = glob(database_path('migrations/*_create_'.$table.'_table.php'));
        $migrations[] = require $paths[0];
    }

    $normalization = require database_path('migrations/2026_11_13_160924_normalize_municipal_poa_versions.php');
    $terms = require database_path('migrations/2026_11_13_160925_create_municipal_terms_table.php');
    $uploads = require database_path('migrations/2026_11_13_160926_create_municipal_poa_uploads_table.php');
    $uploads->down();
    $terms->down();
    $normalization->down();
    foreach (array_reverse($migrations) as $migration) {
        $migration->down();
    }

    foreach ($tables as $table) {
        expect(Schema::hasTable($table))->toBeFalse();
    }

    $this->assertModelExists($municipality);

    foreach ($migrations as $migration) {
        $migration->up();
    }

    $normalization->up();
    $terms->up();
    $uploads->up();
    expect(MunicipalPoa::factory()->for($municipality)->create()->municipality->is($municipality))->toBeTrue();
});

it('impide insertar registros municipales con padres inexistentes', function (string $model, string $foreignKey): void {
    $attributes = $model::factory()->make()->getAttributes();
    $attributes[$foreignKey] = 999999999;

    expect(fn () => DB::transaction(fn () => DB::table((new $model)->getTable())->insert($attributes)))
        ->toThrow(QueryException::class);
})->with([
    [MunicipalPoa::class, 'municipality_id'],
    [MunicipalPoaDocument::class, 'municipal_poa_version_id'],
    [MunicipalAuthority::class, 'municipality_id'],
]);
