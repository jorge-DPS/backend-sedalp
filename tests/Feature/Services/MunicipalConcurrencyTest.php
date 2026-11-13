<?php

use App\Models\Municipal\MunicipalAuthority;
use App\Models\Municipal\MunicipalPoa;
use App\Models\Municipal\MunicipalTerm;
use App\Models\Territory\Municipality;
use App\Services\Municipal\MunicipalPoaDocumentService;
use App\Services\Municipal\MunicipalPoaVersionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

it('serializa operaciones municipales simultáneas sin perder versiones ni duplicar alcaldes actuales', function (string $operation): void {
    expect(app()->environment('testing'))->toBeTrue()
        ->and(DB::selectOne('SELECT current_database() AS name')->name)->toBe('sedalp_testing');

    $schema = 'simred_municipal_test_'.Str::lower(Str::random(16));
    $originalConnection = DB::getDefaultConnection();
    $configuration = config('database.connections.pgsql');
    $originalRoot = config('filesystems.disks.local.root');
    $root = storage_path('framework/testing/disks/'.$schema);
    $setup = null;
    $processes = [];
    $paths = [];
    config(['database.connections.municipal_concurrency' => $configuration]);
    DB::connection('municipal_concurrency')->statement('CREATE SCHEMA "'.$schema.'"');

    try {
        DB::purge('municipal_concurrency');
        config(['database.connections.municipal_concurrency.search_path' => $schema, 'filesystems.disks.local.root' => $root]);
        Storage::forgetDisk('local');
        DB::setDefaultConnection('municipal_concurrency');
        Schema::clearResolvedInstance('db.schema');
        foreach (['positions', 'professions', 'organizational_units', 'staff_members', 'users', 'regions', 'provinces', 'municipalities', 'municipal_poas', 'municipal_poa_documents', 'municipal_authorities'] as $table) {
            $migrationPaths = glob(database_path('migrations/*_create_'.$table.'_table.php'));
            $migration = require $migrationPaths[0];
            $migration->up();
        }

        foreach (['2026_11_13_160924_normalize_municipal_poa_versions.php', '2026_11_13_160925_create_municipal_terms_table.php', '2026_11_13_160926_create_municipal_poa_uploads_table.php'] as $migrationPath) {
            $migration = require database_path('migrations/'.$migrationPath);
            DB::transaction(fn () => $migration->up());
        }

        $municipality = Municipality::factory()->create();
        $poa = MunicipalPoa::factory()->for($municipality)->create();
        $payloads = [];

        if (in_array($operation, ['register', 'publish'], true)) {
            $version = app(MunicipalPoaVersionService::class)->create($poa);
            foreach ([0, 1] as $index) {
                $path = 'municipal/poas/'.$poa->id.'/'.Str::uuid().'.pdf';
                $paths[] = $path;
                Storage::disk('local')->put($path, "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF", 'private');
                if ($operation === 'publish') {
                    $version = $index === 0 ? $version : app(MunicipalPoaVersionService::class)->create($poa);
                    $document = app(MunicipalPoaDocumentService::class)->register($version, ['title' => 'Documento '.$index, 'pdf_path' => $path]);
                    $payloads[] = ['version_id' => $version->id];
                } else {
                    $payloads[] = ['title' => 'Documento '.$index, 'pdf_path' => $path, 'version_id' => $version->id];
                }
            }
        } elseif ($operation === 'create_version') {
            $payloads = [[], []];
        } elseif ($operation === 'mayor') {
            $term = MunicipalTerm::factory()->for($municipality)->create();
            $payloads = [
                ['full_name' => 'Alcalde A', 'role' => 'mayor', 'is_current' => true, 'municipal_term_id' => $term->id],
                ['full_name' => 'Alcalde B', 'role' => 'mayor', 'is_current' => true, 'municipal_term_id' => $term->id],
            ];
        } else {
            $previous = MunicipalAuthority::factory()->for($municipality)->mayor()->current()->create(['term_start' => '2020-01-01']);
            foreach ([0, 1] as $index) {
                $candidate = MunicipalAuthority::factory()->for($municipality)->mayor()->create(['term_start' => today()->toDateString()]);
                $payloads[] = [
                    'candidate_id' => $candidate->id, 'previous_authority_id' => $previous->id,
                    'previous_term_end' => today()->subDay()->toDateString(),
                ];
            }
        }

        $setup = DB::connection('municipal_concurrency');
        DB::setDefaultConnection($originalConnection);
        Schema::clearResolvedInstance('db.schema');
        $worker = <<<'PHP'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->loadEnvironmentFrom('.env.testing');
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (! $app->environment('testing') || config('database.connections.pgsql.database') !== 'sedalp_testing') {
    throw new RuntimeException('Esta prueba solo puede ejecutarse en sedalp_testing.');
}
config(['database.connections.pgsql.search_path' => $argv[1], 'filesystems.disks.local.root' => $argv[2]]);
Illuminate\Support\Facades\DB::purge('pgsql');
Illuminate\Support\Facades\Storage::forgetDisk('local');
if (Illuminate\Support\Facades\DB::selectOne('SELECT current_database() AS name')->name !== 'sedalp_testing') {
    throw new RuntimeException('Base de pruebas incorrecta.');
}
Illuminate\Support\Facades\DB::select("SELECT set_config('application_name', ?, false)", [$argv[3]]);
$payload = json_decode($argv[6], true, 512, JSON_THROW_ON_ERROR);
try {
    switch ($argv[4]) {
        case 'register':
            app(App\Services\Municipal\MunicipalPoaDocumentService::class)->register(App\Models\Municipal\MunicipalPoaVersion::findOrFail($payload['version_id']), Illuminate\Support\Arr::except($payload, ['version_id']));
            break;
        case 'publish':
            app(App\Services\Municipal\MunicipalPoaVersionService::class)->publish(App\Models\Municipal\MunicipalPoaVersion::findOrFail($payload['version_id']));
            break;
        case 'create_version':
            app(App\Services\Municipal\MunicipalPoaVersionService::class)->create(App\Models\Municipal\MunicipalPoa::findOrFail((int) $argv[5]));
            break;
        case 'mayor':
            app(App\Services\Municipal\MunicipalAuthorityService::class)->create(App\Models\Territory\Municipality::findOrFail((int) $argv[5]), $payload);
            break;
        case 'transition':
            app(App\Services\Municipal\MunicipalAuthorityService::class)->activateMayor(App\Models\Municipal\MunicipalAuthority::findOrFail($payload['candidate_id']), $payload);
            break;
    }
    echo 'success';
} catch (Illuminate\Validation\ValidationException $exception) {
    echo 'validation';
}
PHP;
        $setup->beginTransaction();
        $setup->table(in_array($operation, ['register', 'publish', 'create_version'], true) ? 'municipal_poas' : 'municipalities')
            ->where('id', in_array($operation, ['register', 'publish', 'create_version'], true) ? $poa->id : $municipality->id)->lockForUpdate()->first();
        $names = [];
        foreach ($payloads as $index => $payload) {
            $names[] = $schema.'_'.$index;
            $process = new Process([
                PHP_BINARY, '-r', $worker, $schema, $root, $names[$index], $operation,
                (string) (in_array($operation, ['register', 'publish', 'create_version'], true) ? $poa->id : $municipality->id),
                json_encode($payload, JSON_THROW_ON_ERROR),
            ], base_path(), ['APP_ENV' => 'testing', 'PAO_DISABLE' => 'true']);
            $process->setTimeout(30);
            $process->start();
            $processes[] = $process;
        }
        $deadline = microtime(true) + 15;
        $waiting = 0;
        do {
            $setup->select('SELECT pg_stat_clear_snapshot()');
            $waiting = $setup->table('pg_stat_activity')->whereIn('application_name', $names)->where('wait_event_type', 'Lock')->count();
            if ($waiting === 2) {
                break;
            }
            usleep(20000);
        } while (microtime(true) < $deadline);

        $details = implode("\n", array_map(fn (Process $process): string => $process->getErrorOutput().$process->getOutput(), $processes));
        $this->assertSame(2, $waiting, $details);
        $setup->commit();
        $results = [];
        foreach ($processes as $process) {
            $process->wait();
            $this->assertTrue($process->isSuccessful(), $process->getErrorOutput().$process->getOutput());
            $results[] = $process->getOutput();
        }

        if (in_array($operation, ['register', 'publish', 'create_version'], true)) {
            expect($results)->toBe(['success', 'success'])
                ->and($setup->table('municipal_poa_versions')->orderBy('version_number')->pluck('version_number')->all())->toBe($operation === 'register' ? [1] : [1, 2])
                ->and($setup->table('municipal_poa_documents')->count())->toBe($operation === 'create_version' ? 0 : 2)
                ->and($setup->table('municipal_poa_versions')->where('is_current', true)->count())->toBe($operation === 'publish' ? 1 : 0);
            foreach ($paths as $path) {
                Storage::disk('local')->assertExists($path);
            }
        } else {
            sort($results);
            expect($results)->toBe(['success', 'validation'])
                ->and($setup->table('municipal_authorities')->where('role', 'mayor')->where('is_current', true)->count())->toBe(1)
                ->and($setup->table('municipal_authorities')->count())->toBe($operation === 'mayor' ? 1 : 3);
            if ($operation === 'transition') {
                expect($setup->table('municipal_authorities')->where('id', $previous->id)->value('is_current'))->toBeFalse()
                    ->and($setup->table('municipal_authorities')->where('id', $previous->id)->value('term_end'))->toBe(today()->subDay()->toDateString());
            }
        }
    } finally {
        if ($setup !== null && $setup->transactionLevel() > 0) {
            $setup->rollBack();
        }
        foreach ($processes as $process) {
            if ($process->isRunning()) {
                $process->stop(0.2);
            }
        }
        if ($paths !== []) {
            Storage::disk('local')->delete($paths);
        }
        config(['filesystems.disks.local.root' => $originalRoot]);
        Storage::forgetDisk('local');
        DB::setDefaultConnection($originalConnection);
        Schema::clearResolvedInstance('db.schema');
        DB::purge('municipal_concurrency');
        config(['database.connections.municipal_concurrency' => $configuration]);
        if (preg_match('/^simred_municipal_test_[a-z0-9]{16}$/D', $schema) === 1) {
            DB::connection('municipal_concurrency')->statement('DROP SCHEMA "'.$schema.'" CASCADE');
        }
        DB::purge('municipal_concurrency');
    }
})->with(['register', 'publish', 'create_version', 'mayor', 'transition']);
