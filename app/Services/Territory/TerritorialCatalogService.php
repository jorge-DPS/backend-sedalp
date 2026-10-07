<?php

namespace App\Services\Territory;

use App\Models\Territory\Province;
use App\Models\Territory\Region;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class TerritorialCatalogService
{
    public function delete(Region|Province $catalog): void
    {
        DB::transaction(function () use ($catalog): void {
            $catalog = $catalog->newQuery()->lockForUpdate()->findOrFail($catalog->getKey());

            if ($catalog->municipalities()->withTrashed()->exists()) {
                throw new ConflictHttpException(
                    'El registro tiene municipios asociados. Desactívelo en lugar de eliminarlo.'
                );
            }

            $catalog->delete();
        });
    }
}
