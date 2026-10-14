<?php

use App\Models\User;
use App\Services\Territory\MapFileService;
use App\Services\Territory\MapLayerService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\Process\Process;

beforeEach(function () {
    Storage::fake('local');
});

function mapZip(array $entries): UploadedFile
{
    $path = Storage::disk('local')->path('source-'.Str::uuid().'.zip');
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE);
    foreach ($entries as $name => $contents) {
        $zip->addFromString($name, $contents);
    }
    $zip->close();

    return new UploadedFile($path, 'layer.zip', 'application/zip', null, true);
}

it('rechaza ZIP incompletos rutas peligrosas y archivos adicionales', function (array $entries) {
    expect(fn () => app(MapFileService::class)->read(mapZip($entries)))->toThrow(ValidationException::class);
    expect(File::directories(Storage::disk('local')->path('maps/temporary')))->toBe([]);
})->with([
    [['test.shp' => 'incomplete']],
    [['../test.shp' => 'unsafe']],
    [['test.gpkg' => 'unsupported']],
    [['a.shp' => 'one', 'b.dbf' => 'another']],
    [['test.shp' => str_repeat('x', 100000)]],
]);

it('convierte un Shapefile proyectado a coordenadas geográficas y limpia temporales', function () {
    $binary = config('maps.ogr2ogr_binary');
    if (! is_file($binary)) {
        $this->markTestSkipped('Configure MAPS_OGR2OGR_BINARY para verificar GDAL.');
    }
    $directory = Storage::disk('local')->path('fixture');
    File::ensureDirectoryExists($directory);
    File::put($directory.'/point.geojson', '{"type":"Feature","properties":{"name":"Estación"},"geometry":{"type":"Point","coordinates":[-68.15,-16.5]}}');
    (new Process([$binary, '-f', 'ESRI Shapefile', '-t_srs', 'EPSG:32719', $directory.'/point.shp', $directory.'/point.geojson']))->mustRun();
    $entries = [];
    foreach (['shp', 'shx', 'dbf', 'prj'] as $extension) {
        $entries['point.'.$extension] = File::get($directory.'/point.'.$extension);
    }
    $result = app(MapFileService::class)->read(mapZip($entries), true);
    expect($result['summary']['srid'])->toBe(4326)
        ->and($result['features'][0]['geometry']['coordinates'][0])->toEqualWithDelta(-68.15, 0.00001)
        ->and(File::directories(Storage::disk('local')->path('maps/temporary')))->toBe([]);
});

it('valida los archivos regionales locales cuando están disponibles', function () {
    $directory = env('MAPS_TEST_REGIONS_DIRECTORY');
    if (! $directory || ! is_dir($directory)) {
        $this->markTestSkipped('Archivos regionales locales opcionales no configurados.');
    }
    $count = 0;
    foreach (File::allFiles($directory) as $source) {
        if (strtolower($source->getExtension()) !== 'shp') {
            continue;
        }
        $base = substr($source->getPathname(), 0, -4);
        $entries = [];
        foreach (['shp', 'shx', 'dbf', 'prj', 'cpg'] as $extension) {
            if (is_file($base.'.'.$extension)) {
                $entries[basename($base).'.'.$extension] = File::get($base.'.'.$extension);
            }
        }
        $result = app(MapFileService::class)->read(mapZip($entries), true);
        expect($result['summary']['feature_count'])->toBeGreaterThan(0)
            ->and(array_keys($result['summary']['geometry_types']))->each->toBeIn(['Polygon', 'MultiPolygon']);
        $layer = app(MapLayerService::class)->save(mapZip($entries), [
            'name' => basename($base), 'kind' => 'overlay', 'repair_geometries' => true,
        ], User::factory()->create()->id);
        expect($layer->currentVersion->features()->count())->toBe($result['summary']['feature_count']);
        $count++;
    }
    expect($count)->toBeGreaterThan(0);
});

it('informa cuando el conversor no está instalado y elimina los temporales', function () {
    config(['maps.ogr2ogr_binary' => 'missing-map-converter-12345']);
    expect(fn () => app(MapFileService::class)->read(mapZip([
        'test.shp' => 'test', 'test.shx' => 'test', 'test.dbf' => 'test', 'test.prj' => 'test',
    ])))->toThrow(ServiceUnavailableHttpException::class);
    expect(File::directories(Storage::disk('local')->path('maps/temporary')))->toBe([]);
});

it('rechaza una ruta PROJ incorrecta como fallo del servidor', function () {
    config(['maps.proj_data' => Storage::disk('local')->path('missing-proj')]);
    expect(fn () => app(MapFileService::class)->read(mapZip([
        'test.shp' => 'test', 'test.shx' => 'test', 'test.dbf' => 'test', 'test.prj' => 'test',
    ])))->toThrow(ServiceUnavailableHttpException::class, 'catálogo de proyecciones');
    expect(File::directories(Storage::disk('local')->path('maps/temporary')))->toBe([]);
});

it('utiliza PROJ configurado cuando el servidor hereda una ruta incorrecta', function () {
    $directory = env('MAPS_TEST_REGIONS_DIRECTORY');
    $path = $directory.'/reg_Altiplano Norte/reg_Altiplano Norte.zip';
    if (! $directory || ! is_file($path) || ! config('maps.proj_data')) {
        $this->markTestSkipped('ZIP y catálogo PROJ locales opcionales no disponibles.');
    }
    $variables = ['PROJ_DATA', 'PROJ_LIB'];
    $previous = [];
    foreach ($variables as $variable) {
        $previous[$variable] = [getenv($variable), $_ENV[$variable] ?? null, $_SERVER[$variable] ?? null];
        putenv($variable.'=missing-proj-directory');
        $_ENV[$variable] = 'missing-proj-directory';
        $_SERVER[$variable] = 'missing-proj-directory';
    }
    try {
        $result = app(MapFileService::class)->read(new UploadedFile($path, basename($path), 'application/zip', null, true));
        expect($result['summary']['feature_count'])->toBe(1)
            ->and($result['summary']['srid'])->toBe(4326)
            ->and($result['summary']['repaired_features'])->toBe(0);
    } finally {
        foreach ($previous as $variable => [$value, $environment, $server]) {
            putenv($value === false ? $variable : $variable.'='.$value);
            if ($environment === null) {
                unset($_ENV[$variable]);
            } else {
                $_ENV[$variable] = $environment;
            }
            if ($server === null) {
                unset($_SERVER[$variable]);
            } else {
                $_SERVER[$variable] = $server;
            }
        }
    }
});
