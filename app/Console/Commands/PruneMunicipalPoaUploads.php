<?php

namespace App\Console\Commands;

use App\Services\Municipal\MunicipalPoaUploadService;
use Illuminate\Console\Command;

class PruneMunicipalPoaUploads extends Command
{
    protected $signature = 'municipal:prune-poa-uploads';

    protected $description = 'Retira sesiones POA expiradas y sus archivos temporales sin eliminar documentos registrados.';

    public function handle(MunicipalPoaUploadService $uploads): int
    {
        $this->info('Cargas temporales retiradas: '.$uploads->prune());

        return self::SUCCESS;
    }
}
