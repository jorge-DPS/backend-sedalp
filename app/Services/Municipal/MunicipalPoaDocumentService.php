<?php

namespace App\Services\Municipal;

use App\Enums\Municipal\PoaDocumentType;
use App\Models\Municipal\MunicipalPoa;
use App\Models\Municipal\MunicipalPoaDocument;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class MunicipalPoaDocumentService
{
    /**
     * Registra metadatos de un PDF ya almacenado en el disco privado local.
     *
     * @param  array{title: string, pdf_path: string, document_reference?: string|null, original_filename?: string|null, received_at?: string|null, notes?: string|null}  $data
     */
    public function register(MunicipalPoa $poa, array $data): MunicipalPoaDocument
    {
        $values = Validator::make($data, [
            'title' => ['required', 'string', 'max:255'],
            'pdf_path' => ['required', 'string', 'max:255', 'regex:~^municipal/poas/[A-Za-z0-9/_-]+\\.pdf$~D'],
            'document_reference' => ['sometimes', 'nullable', 'string', 'max:255'],
            'original_filename' => ['sometimes', 'nullable', 'string', 'max:255'],
            'received_at' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ])->validate();

        $this->validateStoredPdf($values['pdf_path']);

        return DB::transaction(function () use ($poa, $values): MunicipalPoaDocument {
            $poa = MunicipalPoa::query()->lockForUpdate()->findOrFail($poa->getKey());

            if (MunicipalPoaDocument::where('pdf_path', $values['pdf_path'])->exists()) {
                throw ValidationException::withMessages(['pdf_path' => 'Cada versión debe conservar un archivo propio sin sobrescribir PDFs históricos.']);
            }

            $number = (int) $poa->documents()->max('version_number') + 1;

            return $poa->documents()->create([
                ...$values,
                'version_number' => $number,
                'document_type' => $number === 1 ? PoaDocumentType::INITIAL : PoaDocumentType::REFORMULATED,
                'mime_type' => 'application/pdf',
                'file_size_bytes' => Storage::disk('local')->size($values['pdf_path']),
            ]);
        });
    }

    public function publish(MunicipalPoaDocument $document, bool $makeCurrent = true): MunicipalPoaDocument
    {
        return DB::transaction(function () use ($document, $makeCurrent): MunicipalPoaDocument {
            $poa = MunicipalPoa::query()->lockForUpdate()->findOrFail($document->municipal_poa_id);
            $document = $poa->documents()->lockForUpdate()->findOrFail($document->getKey());
            $this->validateStoredPdf($document->pdf_path);

            if ($makeCurrent) {
                $poa->documents()->whereKeyNot($document->id)->update(['is_current' => false]);
            }

            if (! $document->update([
                'published_at' => $document->isPubliclyAvailable() ? $document->published_at : now(),
                'is_current' => $makeCurrent || $document->is_current,
            ])) {
                throw new RuntimeException('No se pudo publicar el documento.');
            }

            return $document->refresh();
        });
    }

    private function validateStoredPdf(string $path): void
    {
        if (! Storage::disk('local')->exists($path) || Storage::disk('local')->mimeType($path) !== 'application/pdf') {
            throw ValidationException::withMessages(['pdf_path' => 'El archivo debe ser un PDF existente en el almacenamiento privado.']);
        }

        $stream = Storage::disk('local')->readStream($path);

        if (! is_resource($stream)) {
            throw ValidationException::withMessages(['pdf_path' => 'No se puede leer el PDF privado.']);
        }

        try {
            $isPdf = fread($stream, 5) === '%PDF-';
        } finally {
            fclose($stream);
        }

        if (! $isPdf) {
            throw ValidationException::withMessages(['pdf_path' => 'El contenido del archivo no tiene una cabecera PDF válida.']);
        }
    }
}
