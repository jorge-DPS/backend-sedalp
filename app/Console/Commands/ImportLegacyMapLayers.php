<?php

namespace App\Console\Commands;

use App\Models\Territory\RegionBoundary;
use App\Models\User;
use App\Services\Territory\LegacyMapLayerService;
use Illuminate\Console\Command;

class ImportLegacyMapLayers extends Command
{
    protected $signature = 'maps:import-legacy {--user= : ID del administrador que incorpora los límites}';

    protected $description = 'Incorpora límites regionales sin capa como borradores, conservando los registros originales.';

    public function handle(LegacyMapLayerService $layers): int
    {
        $user = filled($this->option('user'))
            ? User::query()->find($this->option('user'))
            : User::query()->where('email', config('simred.super_admin.email'))->first();
        if ($user === null || ! $user->canAccessApi() || ! $user->can('maps.create') || ! $user->can('regions.update')) {
            $this->error('Indique --user con un administrador activo autorizado para mapas y regiones.');

            return self::FAILURE;
        }
        $count = 0;
        foreach (RegionBoundary::query()->select(['id', 'region_id'])->orderBy('id')->cursor() as $boundary) {
            if ($layers->import($boundary->region_id, $user->id)) {
                $count++;
            }
        }
        $this->info("Límites incorporados como borrador: {$count}. No se publicó ninguna capa.");

        return self::SUCCESS;
    }
}
