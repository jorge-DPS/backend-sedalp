<?php

namespace App\Console\Commands;

use App\Models\Territory\Region;
use App\Services\Territory\RegionBoundaryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;
use Throwable;

class ImportRegionBoundary extends Command
{
    protected $signature = 'territory:import-region-boundary
        {region : ID de una región existente}
        {file : Ruta local al archivo .shp}
        {--source-srid= : Código EPSG de origen verificado en el archivo .prj}
        {--ogr2ogr=ogr2ogr : Ruta al ejecutable ogr2ogr}
        {--replace : Reemplazar explícitamente el límite existente}
        {--dry-run : Validar la importación sin guardar cambios}';

    protected $description = 'Importa un límite regional Shapefile y lo transforma a GeoJSON WGS84 / PostGIS.';

    public function handle(RegionBoundaryService $service): int
    {
        $path = realpath((string) $this->argument('file'));
        $srid = filter_var($this->option('source-srid'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 998999]]);

        if ($path === false || strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'shp' || $srid === false) {
            $this->error('Indique un archivo .shp existente y --source-srid con su código EPSG.');

            return self::FAILURE;
        }

        $base = substr($path, 0, -4);
        foreach (['shp', 'shx', 'dbf', 'prj'] as $extension) {
            if (! is_readable($base.'.'.$extension)) {
                $this->error('Falta un archivo legible: '.$base.'.'.$extension);

                return self::FAILURE;
            }
        }

        if (! ctype_digit((string) $this->argument('region'))) {
            $this->error('El identificador de región debe ser numérico.');

            return self::FAILURE;
        }

        $region = Region::find($this->argument('region'));

        if ($region === null) {
            $this->error('La región no existe o está eliminada.');

            return self::FAILURE;
        }

        $initialTransactionLevel = DB::transactionLevel();

        try {
            $process = new Process([
                (string) $this->option('ogr2ogr'),
                '-f', 'GeoJSON', '-s_srs', 'EPSG:'.$srid, '-t_srs', 'EPSG:4326',
                '-dim', 'XY', '-lco', 'RFC7946=YES', '/vsistdout/', $path,
            ]);
            $process->setTimeout(120);
            $process->mustRun();
            $collection = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);

            if (! is_array($collection)) {
                throw new \RuntimeException('El conversor no devolvió un GeoJSON válido.');
            }

            DB::beginTransaction();
            $boundary = $service->import($region, $collection, $path, $srid, (bool) $this->option('replace'));

            if ($this->option('dry-run')) {
                DB::rollBack();
                $this->info('Geometría válida. Simulación completada sin guardar cambios.');
            } else {
                DB::commit();
                $this->info('Límite importado para '.$region->name.' (ID '.$boundary->id.').');
            }

            return self::SUCCESS;
        } catch (Throwable $exception) {
            while (DB::transactionLevel() > $initialTransactionLevel) {
                DB::rollBack();
            }

            $message = $exception instanceof ValidationException
                ? implode(' ', $exception->validator->errors()->all())
                : $exception->getMessage();
            $this->error($message);

            return self::FAILURE;
        }
    }
}
