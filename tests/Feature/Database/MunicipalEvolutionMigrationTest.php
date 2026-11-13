<?php

use App\Models\Municipal\MunicipalAuthority;
use App\Models\Municipal\MunicipalPoa;
use App\Models\Municipal\MunicipalPoaDocument;
use App\Models\Municipal\MunicipalPoaUpload;
use App\Models\Municipal\MunicipalPoaVersion;
use App\Models\Municipal\MunicipalTerm;
use App\Models\Territory\Municipality;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function (): void {
    expect(DB::connection()->getDatabaseName())->toBe('sedalp_testing');
    Storage::fake('local');
    $this->normalization = require database_path('migrations/2026_11_13_160924_normalize_municipal_poa_versions.php');
    $this->termsMigration = require database_path('migrations/2026_11_13_160925_create_municipal_terms_table.php');
    $this->uploadsMigration = require database_path('migrations/2026_11_13_160926_create_municipal_poa_uploads_table.php');
});

it('transforma cada PDF histórico sin agrupar por conjeturas y permite una reversión exacta', function (): void {
    $municipality = Municipality::factory()->create();
    $poa = MunicipalPoa::factory()->for($municipality)->create();
    $this->uploadsMigration->down();
    $this->termsMigration->down();
    $this->normalization->down();
    $authority = MunicipalAuthority::factory()->for($municipality)->create(['term_start' => null, 'term_end' => null]);
    $authorityBefore = $authority->fresh()->getAttributes();
    $before = [];
    foreach ([[1, null, false], [2, now()->subDay()->toDateTimeString(), true], [4, now()->addDay()->toDateTimeString(), false]] as [$number, $published, $current]) {
        $path = 'municipal/poas/'.$poa->id.'/'.Str::uuid().'.pdf';
        $bytes = "%PDF-1.4\nHistórico ".$number;
        Storage::disk('local')->put($path, $bytes);
        $id = DB::table('municipal_poa_documents')->insertGetId([
            'municipal_poa_id' => $poa->id, 'version_number' => $number,
            'document_type' => $number === 1 ? 'initial' : 'reformulated', 'published_at' => $published, 'is_current' => $current,
            'title' => 'Documento '.$number, 'document_reference' => 'Referencia '.$number, 'pdf_path' => $path,
            'original_filename' => 'Documento original '.$number.'.pdf', 'mime_type' => 'application/pdf',
            'file_size_bytes' => strlen($bytes), 'received_at' => '2025-01-02', 'notes' => 'Nota histórica',
            'created_at' => '2025-01-03 10:20:30', 'updated_at' => '2025-01-04 10:20:30',
        ]);
        $before[$id] = (array) DB::table('municipal_poa_documents')->find($id);
    }

    for ($cycle = 0; $cycle < 2; $cycle++) {
        $this->normalization->up();
        $this->termsMigration->up();
        $this->uploadsMigration->up();
        expect(Schema::hasColumn('municipal_poa_documents', 'published_at'))->toBeFalse()
            ->and(Schema::hasColumn('municipal_poa_documents', 'municipal_poa_id'))->toBeFalse()
            ->and($poa->versions()->count())->toBe(3)
            ->and($poa->versions()->orderBy('version_number')->pluck('version_number')->all())->toBe([1, 2, 4])
            ->and($authority->fresh()->municipal_term_id)->toBeNull()
            ->and(collect($authority->fresh()->getAttributes())->except('municipal_term_id')->all())->toEqual($authorityBefore);
        foreach ($before as $id => $row) {
            $document = MunicipalPoaDocument::findOrFail($id);
            foreach (['municipal_poa_id', 'version_number', 'document_type', 'published_at', 'is_current'] as $field) {
                expect($document->version->getRawOriginal($field))->toBe($row[$field]);
            }
            expect(collect($document->getAttributes())->except('municipal_poa_version_id')->all())
                ->toEqual(collect($row)->except(['municipal_poa_id', 'version_number', 'document_type', 'published_at', 'is_current'])->all());
            expect(Storage::disk('local')->get($row['pdf_path']))->toBe("%PDF-1.4\nHistórico ".$row['version_number']);
        }
        $this->uploadsMigration->down();
        $this->termsMigration->down();
        $this->normalization->down();
        foreach ($before as $id => $row) {
            expect((array) DB::table('municipal_poa_documents')->find($id))->toEqual($row);
        }
    }
    $this->normalization->up();
    $this->termsMigration->up();
    $this->uploadsMigration->up();
});

it('bloquea down antes de perder versiones vacías o con múltiples PDF', function (bool $empty): void {
    $this->uploadsMigration->down();
    $version = MunicipalPoaVersion::factory()->create();
    if (! $empty) {
        MunicipalPoaDocument::factory()->count(2)->create(['municipal_poa_version_id' => $version->id]);
    }
    expect(fn () => $this->normalization->down())->toThrow(RuntimeException::class, 'perdería versiones');
    expect(Schema::hasColumn('municipal_poa_documents', 'municipal_poa_version_id'))->toBeTrue()
        ->and($version->documents()->count())->toBe($empty ? 0 : 2);
})->with([true, false]);

it('bloquea reversiones que perderían gestiones o sesiones de carga registradas', function (): void {
    MunicipalTerm::factory()->create();
    expect(fn () => $this->termsMigration->down())->toThrow(RuntimeException::class, 'perdería gestiones');
    MunicipalPoaUpload::create([
        'id' => (string) Str::uuid(), 'municipal_poa_version_id' => MunicipalPoaVersion::factory()->create()->id,
        'user_id' => User::factory()->create()->id, 'idempotency_key' => (string) Str::uuid(),
        'expected_size' => 2147483649, 'chunk_size' => 8388608, 'sha256' => str_repeat('0', 64),
        'metadata' => ['document' => [], 'chunks' => []], 'expires_at' => now()->addDay(),
    ]);
    expect(fn () => $this->uploadsMigration->down())->toThrow(RuntimeException::class, 'sesiones de carga');
    expect(MunicipalPoaUpload::sole()->expected_size)->toBe(2147483649);
});

it('revierte una falla parcial de transformación de forma transaccional sin tocar el PDF', function (): void {
    $this->uploadsMigration->down();
    $this->normalization->down();
    $poa = MunicipalPoa::factory()->create();
    $path = 'municipal/poas/legacy.pdf';
    Storage::disk('local')->put($path, '%PDF-1.4');
    $id = DB::table('municipal_poa_documents')->insertGetId([
        'municipal_poa_id' => $poa->id, 'version_number' => 1, 'document_type' => 'initial',
        'title' => 'Histórico', 'pdf_path' => $path, 'mime_type' => 'application/pdf',
    ]);
    $before = (array) DB::table('municipal_poa_documents')->find($id);
    Event::listen(QueryExecuted::class, function (QueryExecuted $query): void {
        if (str_starts_with(strtolower($query->sql), 'update municipal_poa_documents d set municipal_poa_version_id')) {
            throw new RuntimeException('Falla parcial simulada.');
        }
    });
    try {
        expect(fn () => DB::transaction(fn () => $this->normalization->up()))->toThrow(RuntimeException::class, 'Falla parcial');
    } finally {
        Event::forget(QueryExecuted::class);
    }
    expect(Schema::hasTable('municipal_poa_versions'))->toBeFalse()
        ->and((array) DB::table('municipal_poa_documents')->find($id))->toEqual($before)
        ->and(Storage::disk('local')->get($path))->toBe('%PDF-1.4');
    $this->normalization->up();
    $this->uploadsMigration->up();
});
