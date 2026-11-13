<?php

namespace App\Services\Municipal;

use App\Jobs\Municipal\CleanupMunicipalUpload;
use App\Models\Municipal\MunicipalPoaDocument;
use App\Models\Municipal\MunicipalPoaVersion;
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
    public function __construct(private readonly MunicipalPoaVersionService $versions) {}

    /** @param array<string, mixed> $data */
    public function upload(MunicipalPoaVersion $version, UploadedFile $pdf, array $data): MunicipalPoaDocument
    {
        $directory = 'municipal/poas/'.$version->municipal_poa_id;
        $filename = Str::uuid().'.pdf';
        $path = $directory.'/'.$filename;
        try {
            DB::transaction(fn () => $this->versions->lockDraft($version));
            if ($pdf->storeAs($directory, $filename, ['disk' => 'local', 'visibility' => 'private']) === false) {
                throw new RuntimeException('No se pudo almacenar el PDF privado.');
            }

            return $this->register($version, [
                ...$data, 'pdf_path' => $path,
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

    /** @param array<string, mixed> $data */
    public function register(MunicipalPoaVersion $version, array $data): MunicipalPoaDocument
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

        return DB::transaction(function () use ($version, $values): MunicipalPoaDocument {
            $version = $this->versions->lockDraft($version);
            if (MunicipalPoaDocument::where('pdf_path', $values['pdf_path'])->exists()) {
                throw ValidationException::withMessages(['pdf_path' => 'Cada documento debe conservar un archivo propio.']);
            }
            $document = $version->documents()->create([
                ...$values, 'mime_type' => 'application/pdf',
                'file_size_bytes' => Storage::disk('local')->size($values['pdf_path']),
            ]);
            if (! $document->exists) {
                throw new RuntimeException('No se pudo registrar el documento.');
            }

            return $document;
        }, 3);
    }

    public function assertReadable(MunicipalPoaDocument $document): void
    {
        $size = $this->validateStoredPdf($document->pdf_path);
        if ($document->file_size_bytes !== null && $document->file_size_bytes !== $size) {
            throw ValidationException::withMessages(['pdf_path' => 'El tamaño del archivo no coincide con sus metadatos.']);
        }
    }

    private function validateStoredPdf(string $path): int
    {
        if (preg_match('~^municipal/poas/[A-Za-z0-9/_-]+\\.pdf$~D', $path) !== 1 || ! Storage::disk('local')->exists($path)) {
            throw ValidationException::withMessages(['pdf_path' => 'El PDF privado no está disponible.']);
        }
        $stream = Storage::disk('local')->readStream($path);
        if (! is_resource($stream)) {
            throw ValidationException::withMessages(['pdf_path' => 'No se puede leer el PDF privado.']);
        }
        try {
            $header = fread($stream, 8192);
        } finally {
            fclose($stream);
        }
        if (! is_string($header) || ! str_starts_with($header, '%PDF-')
            || (new \finfo(FILEINFO_MIME_TYPE))->buffer($header) !== 'application/pdf') {
            throw ValidationException::withMessages(['pdf_path' => 'El contenido no corresponde a un PDF válido.']);
        }

        return Storage::disk('local')->size($path);
    }
}
