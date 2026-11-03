<?php

namespace App\Console\Commands;

use App\Models\Territory\Municipality;
use App\Services\Territory\MunicipalityRegionMembershipService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class InitializeMunicipalityRegionMemberships extends Command
{
    protected $signature = 'territory:initialize-region-memberships
        {--from= : Inicio conocido del historial en formato YYYY-MM-DD; no es la fecha oficial de incorporación}
        {--municipality=* : IDs de municipios; si se omite, procesa todos los municipios no eliminados}';

    protected $description = 'Registra puntos de partida regionales sin acuerdos históricos inventados ni cambios de región.';

    public function handle(MunicipalityRegionMembershipService $memberships): int
    {
        $validator = Validator::make([
            'valid_from' => $this->option('from'),
            'municipalities' => $this->option('municipality'),
        ], [
            'valid_from' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'municipalities' => ['array'],
            'municipalities.*' => ['required', 'integer', 'min:1'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        /** @var array{valid_from: string, municipalities: list<string>} $data */
        $data = $validator->validated();
        $municipalities = Municipality::query();

        if ($data['municipalities'] !== []) {
            $municipalities->whereKey($data['municipalities']);

            if ((clone $municipalities)->count() !== count(array_unique($data['municipalities']))) {
                $this->error('Uno o más municipios no existen o están eliminados. No se inicializó ningún registro.');

                return self::FAILURE;
            }
        }

        $created = 0;
        $skipped = 0;

        foreach ($municipalities->lazyById(200) as $municipality) {
            if ($memberships->initialize($municipality, $data['valid_from'])) {
                $created++;
            } else {
                $skipped++;
            }
        }

        $this->info("Registros iniciales creados: {$created}. Municipios con historial conservado: {$skipped}.");
        $this->comment('La fecha indicada es el inicio conocido del historial; no acredita la fecha oficial de incorporación.');

        return self::SUCCESS;
    }
}
