<?php

namespace App\Services\Municipal;

use App\Enums\Municipal\PoaDocumentType;
use App\Jobs\Municipal\CleanupMunicipalUpload;
use App\Models\Municipal\MunicipalPoa;
use App\Models\Municipal\MunicipalPoaDocument;
use App\Models\Territory\Municipality;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class MunicipalPoaDocumentService
{
    /** @param array{title: string, document_reference?: string|null, received_at?: string|null, notes?: string|null} $data */
    public function upload(MunicipalPoa $poa, UploadedFile $pdf, array $data): MunicipalPoaDocument
    {
        $directory = 'municipal/poas/'.$poa->id;
        $filename = Str::uuid().'.pdf';
        $path = $directory.'/'.$filename;

        try {
            if ($pdf->storeAs($directory, $filename, ['disk' => 'local', 'visibility' => 'private']) === false) {
                throw new RuntimeException('No se pudo almacenar el PDF privado.');
            }

            return $this->register($poa, [
                ...$data,
                'pdf_path' => $path,
                'original_filename' => Str::limit(basename(str_replace('\\', '/', $pdf->getClientOriginalName())), 255, ''),
            ]);
        } catch (Throwable $exception) {
            try {
                (new CleanupMunicipalUpload($path))->handle();
            } catch (Throwable $cleanupException) {
                report($cleanupException);
                try {
                    CleanupMunicipalUpload::dispatch($path);
                } catch (Throwable $dispatchException) {
                    report($dispatchException);
                }
            }
            if ($exception instanceof ValidationException && isset($exception->errors()['pdf_path'])) {
                $errors = $exception->errors();
                $errors['pdf'] = $errors['pdf_path'];
                unset($errors['pdf_path']);
                throw ValidationException::withMessages($errors);
            }
            throw $exception;
        }
    }

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
            $this->lockMunicipality($poa);
            $poa = MunicipalPoa::query()->lockForUpdate()->findOrFail($poa->getKey());

            if (MunicipalPoaDocument::where('pdf_path', $values['pdf_path'])->exists()) {
                throw ValidationException::withMessages(['pdf_path' => 'Cada versión debe conservar un archivo propio sin sobrescribir PDFs históricos.']);
            }

            $number = (int) $poa->documents()->max('version_number') + 1;

            $document = $poa->documents()->create([
                ...$values,
                'version_number' => $number,
                'document_type' => $number === 1 ? PoaDocumentType::INITIAL : PoaDocumentType::REFORMULATED,
                'mime_type' => 'application/pdf',
                'file_size_bytes' => Storage::disk('local')->size($values['pdf_path']),
            ]);

            if (! $document->exists) {
                throw new RuntimeException('No se pudo registrar el documento.');
            }

            return $document;
        }, 3);
    }

    public function publish(MunicipalPoaDocument $document, bool $makeCurrent = true): MunicipalPoaDocument
    {
        return DB::transaction(function () use ($document, $makeCurrent): MunicipalPoaDocument {
            $this->lockMunicipality($document->municipalPoa);
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
        }, 3);
    }

    public function assertReadable(MunicipalPoaDocument $document): void
    {
        $this->validateStoredPdf($document->pdf_path);
    }

    private function lockMunicipality(MunicipalPoa $poa): void
    {
        if (Municipality::query()->where('active', true)->sharedLock()->find($poa->municipality_id) === null) {
            throw ValidationException::withMessages(['municipality_id' => 'El municipio no está disponible.']);
        }
    }

    private function validateStoredPdf(string $path): void
    {
        if (preg_match('~^municipal/poas/[A-Za-z0-9/_-]+\\.pdf$~D', $path) !== 1
            || ! Storage::disk('local')->exists($path) || Storage::disk('local')->mimeType($path) !== 'application/pdf') {
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
