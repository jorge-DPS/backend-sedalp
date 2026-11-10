<?php

namespace App\Services\Municipal\Public;

use App\Models\Municipal\MunicipalAuthority;
use App\Models\Municipal\MunicipalPoa;
use App\Models\Municipal\MunicipalPoaDocument;
use App\Models\Territory\Municipality;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MunicipalPortalService
{
    public function municipality(int $id): Municipality
    {
        return Municipality::query()->publiclyAvailable()->with(['region', 'province'])->findOrFail($id);
    }

    /** @return HasMany<MunicipalPoa, Municipality> */
    public function poas(Municipality $municipality): HasMany
    {
        return $this->municipality($municipality->id)->municipalPoas()
            ->whereHas('documents', fn (Builder $query): Builder => $query->publiclyAvailable())
            ->withCount(['documents as published_documents_count' => fn (Builder $query): Builder => $query->publiclyAvailable()]);
    }

    /** @return HasMany<MunicipalPoaDocument, MunicipalPoa> */
    public function documents(Municipality $municipality, MunicipalPoa $poa): HasMany
    {
        $poa = $this->poas($municipality)->findOrFail($poa->id);

        return $poa->documents()->publiclyAvailable();
    }

    /** @return HasMany<MunicipalAuthority, Municipality> */
    public function authorities(Municipality $municipality): HasMany
    {
        return $this->municipality($municipality->id)->municipalAuthorities()->published();
    }
}
