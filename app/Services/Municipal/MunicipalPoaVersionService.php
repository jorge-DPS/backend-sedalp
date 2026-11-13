<?php

namespace App\Services\Municipal;

use App\Enums\Municipal\PoaDocumentType;
use App\Models\Municipal\MunicipalPoa;
use App\Models\Municipal\MunicipalPoaVersion;
use App\Models\Territory\Municipality;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class MunicipalPoaVersionService
{
    public function create(MunicipalPoa $poa): MunicipalPoaVersion
    {
        return DB::transaction(function () use ($poa): MunicipalPoaVersion {
            $this->lockMunicipality($poa);
            $poa = MunicipalPoa::query()->lockForUpdate()->findOrFail($poa->id);
            $number = (int) $poa->versions()->max('version_number') + 1;

            return $poa->versions()->create([
                'version_number' => $number,
                'document_type' => $number === 1 ? PoaDocumentType::INITIAL : PoaDocumentType::REFORMULATED,
            ]);
        }, 3);
    }

    public function publish(MunicipalPoaVersion $version): MunicipalPoaVersion
    {
        return DB::transaction(function () use ($version): MunicipalPoaVersion {
            $this->lockMunicipality($version->municipalPoa);
            $poa = MunicipalPoa::query()->lockForUpdate()->findOrFail($version->municipal_poa_id);
            $version = $poa->versions()->lockForUpdate()->findOrFail($version->id);
            if ($version->uploads()->whereNull('document_id')->exists()) {
                throw ValidationException::withMessages(['version' => 'Complete o cancele las cargas pendientes antes de publicar.']);
            }
            $documents = $version->documents()->orderBy('id')->get();
            if ($documents->isEmpty()) {
                throw ValidationException::withMessages(['version' => 'La versión debe contener al menos un PDF completo.']);
            }
            foreach ($documents as $document) {
                app(MunicipalPoaDocumentService::class)->assertReadable($document);
            }
            $poa->versions()->whereKeyNot($version->id)->where('is_current', true)->update(['is_current' => false]);
            $version->published_at ??= now();
            if ($version->published_at->gt(now())) {
                throw ValidationException::withMessages(['version' => 'La versión tiene una publicación futura; revise su programación existente.']);
            }
            $version->is_current = true;
            if (! $version->save()) {
                throw new RuntimeException('No se pudo publicar la versión completa.');
            }

            return $version->refresh()->loadCount('documents');
        }, 3);
    }

    public function lockMunicipality(MunicipalPoa $poa): void
    {
        if (Municipality::query()->where('active', true)->sharedLock()->find($poa->municipality_id) === null) {
            throw ValidationException::withMessages(['municipality_id' => 'El municipio no está disponible.']);
        }
    }

    public function lockDraft(MunicipalPoaVersion $version): MunicipalPoaVersion
    {
        $this->lockMunicipality($version->municipalPoa);
        MunicipalPoa::query()->lockForUpdate()->findOrFail($version->municipal_poa_id);
        $version = MunicipalPoaVersion::query()->lockForUpdate()->findOrFail($version->id);
        if ($version->published_at !== null) {
            throw ValidationException::withMessages(['version' => 'Registre una nueva reformulación; la versión publicada es inmutable.']);
        }

        return $version;
    }
}
