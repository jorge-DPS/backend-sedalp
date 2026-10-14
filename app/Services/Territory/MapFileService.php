<?php

namespace App\Services\Territory;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use ZipArchive;

class MapFileService
{
    public function __construct(private readonly GeoJsonService $geoJson) {}

    /** @return array{type: string, features: array<int, array<string, mixed>>, summary: array<string, mixed>} */
    public function read(UploadedFile $file, bool $repairGeometries = false): array
    {
        if (strtolower($file->getClientOriginalExtension()) !== 'zip') {
            return $this->geoJson->validate($file->getContent(), $repairGeometries);
        }

        $directory = Storage::disk('local')->path('maps/temporary/'.Str::uuid());
        File::ensureDirectoryExists($directory, 0700);
        try {
            $this->extract($file, $directory);
            $binary = config('maps.ogr2ogr_binary');
            $binary = is_file($binary) ? $binary : (new ExecutableFinder)->find($binary);
            if ($binary === null) {
                throw new ServiceUnavailableHttpException(null, 'El conversor cartográfico no está configurado en el servidor.');
            }
            $output = '';
            $process = new Process([
                $binary, '-if', 'ESRI Shapefile', '-f', 'GeoJSON',
                '-t_srs', 'EPSG:4326', '-dim', 'XY', '-lco', 'RFC7946=YES',
                '/vsistdout/', $directory.'/source.shp',
            ], null, $this->processEnvironment());
            $process->setTimeout(config('maps.conversion_timeout'));
            try {
                $process->mustRun(function (string $type, string $buffer) use (&$output, $process): void {
                    if ($type === Process::OUT) {
                        $output .= $buffer;
                        $process->clearOutput();
                        if (strlen($output) > config('maps.max_geojson_bytes')) {
                            $process->stop(0);
                            throw ValidationException::withMessages(['file' => 'La capa convertida supera el tamaño permitido.']);
                        }
                    }
                });
            } catch (ProcessTimedOutException) {
                throw ValidationException::withMessages(['file' => 'La conversión tardó demasiado. Reduzca el tamaño de la capa.']);
            } catch (ProcessFailedException $exception) {
                Log::warning('No se pudo convertir la capa cartográfica.', [
                    'exit_code' => $process->getExitCode(),
                    'diagnostic' => mb_substr($process->getErrorOutput(), 0, 2000),
                ]);
                if (str_contains($process->getErrorOutput(), 'proj.db')) {
                    throw new ServiceUnavailableHttpException(null, 'El servidor no puede acceder al catálogo de proyecciones. Revise MAPS_PROJ_DATA.');
                }
                if (in_array($process->getExitCode(), [127, 9009], true) || ! is_file($binary) && ! $process->isSuccessful() && str_contains($exception->getMessage(), 'not recognized')) {
                    throw new ServiceUnavailableHttpException(null, 'El conversor cartográfico no está configurado en el servidor.');
                }
                throw ValidationException::withMessages(['file' => 'No se pudo convertir el Shapefile. Revise sus archivos y su proyección .prj.']);
            }

            return $this->geoJson->validate($output, $repairGeometries);
        } finally {
            File::deleteDirectory($directory);
        }
    }

    /** @return array<string, string> */
    private function processEnvironment(): array
    {
        $environment = ['PROJ_NETWORK' => 'OFF'];
        $projData = config('maps.proj_data');
        if (is_string($projData) && $projData !== '') {
            if (! is_readable(rtrim($projData, '/\\').'/proj.db')) {
                throw new ServiceUnavailableHttpException(null, 'El servidor no puede acceder al catálogo de proyecciones. Revise MAPS_PROJ_DATA.');
            }
            $environment['PROJ_DATA'] = $projData;
            $environment['PROJ_LIB'] = $projData;
        }
        $gdalData = config('maps.gdal_data');
        if (is_string($gdalData) && $gdalData !== '') {
            if (! is_dir($gdalData)) {
                throw new ServiceUnavailableHttpException(null, 'El directorio de datos cartográficos del servidor no está disponible. Revise MAPS_GDAL_DATA.');
            }
            $environment['GDAL_DATA'] = $gdalData;
        }

        return $environment;
    }

    private function extract(UploadedFile $file, string $directory): void
    {
        $zip = new ZipArchive;
        if ($zip->open($file->getRealPath()) !== true) {
            throw ValidationException::withMessages(['file' => 'El ZIP no se puede abrir.']);
        }
        try {
            if ($zip->numFiles > 32) {
                $this->invalid('El ZIP contiene demasiados archivos. Incluya una sola capa.');
            }
            $entries = [];
            $stem = null;
            $size = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = $zip->statIndex($i);
                $name = str_replace('\\', '/', $entry['name']);
                if (str_starts_with($name, '/') || str_contains($name, ':') || in_array('..', explode('/', $name), true)) {
                    $this->invalid('El ZIP contiene rutas no permitidas.');
                }
                if (str_ends_with($name, '/')) {
                    continue;
                }
                $zip->getExternalAttributesIndex($i, $system, $attributes);
                if ((($attributes >> 16) & 0170000) === 0120000) {
                    $this->invalid('No se admiten enlaces simbólicos dentro del ZIP.');
                }
                $size += $entry['size'];
                if ($size > config('maps.max_expanded_bytes') || $entry['size'] > max(1, $entry['comp_size']) * 200) {
                    $this->invalid('El ZIP supera los límites de descompresión.');
                }
                $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                if (in_array($extension, ['qmd', 'xml', 'sbn', 'sbx'], true)) {
                    continue;
                }
                if (! in_array($extension, ['shp', 'shx', 'dbf', 'prj', 'cpg'], true)) {
                    $this->invalid('El ZIP solo puede contener los archivos de un Shapefile.');
                }
                $currentStem = strtolower(substr($name, 0, -strlen($extension) - 1));
                if (isset($entries[$extension]) || ($stem !== null && $stem !== $currentStem)) {
                    $this->invalid('Incluya una sola capa con el mismo nombre base para todos sus archivos.');
                }
                $stem = $currentStem;
                $entries[$extension] = $entry['name'];
            }
            foreach (['shp', 'shx', 'dbf', 'prj'] as $required) {
                if (! isset($entries[$required])) {
                    $this->invalid('Falta el archivo .'.$required.' dentro del ZIP.');
                }
            }
            foreach ($entries as $extension => $name) {
                $input = $zip->getStream($name);
                $output = fopen($directory.'/source.'.$extension, 'wb');
                if ($input === false || $output === false) {
                    if (is_resource($input)) {
                        fclose($input);
                    }
                    if (is_resource($output)) {
                        fclose($output);
                    }
                    $this->invalid('No se pudo leer el contenido del ZIP.');
                }
                try {
                    $written = stream_copy_to_stream($input, $output, config('maps.max_expanded_bytes') + 1);
                    if ($written === false || $written > config('maps.max_expanded_bytes') || ! feof($input)) {
                        $this->invalid('El contenido del ZIP supera los límites permitidos.');
                    }
                } finally {
                    fclose($input);
                    fclose($output);
                }
            }
        } finally {
            $zip->close();
        }
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['file' => $message]);
    }
}
