<?php

namespace App\Services\Municipal;

use App\Enums\Auth\PermissionName;
use App\Jobs\Municipal\CleanupMunicipalUpload;
use App\Jobs\Municipal\CompleteMunicipalPoaUpload;
use App\Models\Municipal\MunicipalPoaDocument;
use App\Models\Municipal\MunicipalPoaUpload;
use App\Models\Municipal\MunicipalPoaVersion;
use App\Models\User;
use Closure;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class MunicipalPoaUploadService
{
    public function __construct(
        private readonly MunicipalPoaVersionService $versions,
        private readonly MunicipalPoaDocumentService $documents,
    ) {}

    /** @param array<string, mixed> $data */
    public function start(MunicipalPoaVersion $version, User $user, array $data): MunicipalPoaUpload
    {
        $size = (int) $data['expected_size'];
        $this->ensureCapacity($size);
        $documentData = array_replace(array_fill_keys(['document_reference', 'received_at', 'notes'], null),
            Arr::only($data, ['title', 'original_filename', 'document_reference', 'received_at', 'notes']));

        return DB::transaction(function () use ($version, $user, $data, $size, $documentData): MunicipalPoaUpload {
            User::query()->lockForUpdate()->findOrFail($user->id);
            $existing = MunicipalPoaUpload::query()->where('user_id', $user->id)->where('idempotency_key', $data['idempotency_key'])->first();
            if ($existing !== null) {
                abort_unless($existing->municipal_poa_version_id === $version->id && $existing->expected_size === $size
                    && hash_equals($existing->sha256, $data['sha256'])
                    && Arr::sortRecursive($existing->metadata['document']) === Arr::sortRecursive($documentData), 409,
                    'La clave de idempotencia corresponde a otra carga.');
                $this->ensureAvailable($existing);

                return $existing;
            }
            $this->versions->lockDraft($version);
            if (MunicipalPoaUpload::query()->where('user_id', $user->id)->whereNull('document_id')
                ->where('expires_at', '>', now())->count() >= (int) config('municipal.poa_max_active_uploads')) {
                abort(429, 'Complete o cancele sus cargas pendientes.');
            }

            return MunicipalPoaUpload::create([
                'id' => (string) Str::uuid(), 'municipal_poa_version_id' => $version->id, 'user_id' => $user->id,
                'idempotency_key' => $data['idempotency_key'], 'expected_size' => $size,
                'chunk_size' => $this->chunkSize(), 'sha256' => $data['sha256'],
                'metadata' => ['document' => $documentData, 'chunks' => []],
                'expires_at' => now()->addHours((int) config('municipal.poa_upload_ttl_hours')),
            ]);
        }, 3);
    }

    public function owned(MunicipalPoaUpload $upload, User $user): MunicipalPoaUpload
    {
        abort_unless($upload->user_id === $user->id, 404);
        $this->ensureAvailable($upload);

        return $upload;
    }

    public function put(MunicipalPoaUpload $upload, int $index, UploadedFile $chunk, string $sha256): MunicipalPoaUpload
    {
        return $this->locked($upload->id, function () use ($upload, $index, $chunk, $sha256): MunicipalPoaUpload {
            $upload = $upload->fresh();
            abort_unless($upload instanceof MunicipalPoaUpload, 404);
            $this->ensureAvailable($upload);
            abort_unless(in_array($upload->status, ['receiving', 'failed'], true), 409, 'La carga ya está en proceso o completada.');
            if ($index < 0 || $index >= $upload->chunkCount() || $chunk->getSize() !== $upload->chunkLength($index)) {
                throw ValidationException::withMessages(['chunk' => 'Índice o tamaño de fragmento incorrecto.']);
            }
            if (! hash_equals($sha256, hash_file('sha256', $chunk->getRealPath()))) {
                throw ValidationException::withMessages(['sha256' => 'La integridad del fragmento no coincide.']);
            }
            $metadata = $upload->metadata;
            $previous = $metadata['chunks'][$index] ?? null;
            abort_if($previous !== null && ! hash_equals($previous['sha256'], $sha256), 409, 'El fragmento registrado tiene otro contenido.');
            $path = $this->directory($upload->id).'/'.$index.'.part';
            $disk = Storage::disk('local');
            if ($disk->exists($path) && ($disk->size($path) !== $chunk->getSize() || hash_file('sha256', $disk->path($path)) !== $sha256)) {
                if (! $disk->delete($path)) {
                    throw new RuntimeException('No se pudo retirar el fragmento temporal corrupto.');
                }
            }
            if (! $disk->exists($path)) {
                $input = fopen($chunk->getRealPath(), 'rb');
                $output = fopen($disk->path($path), 'xb');
                if (! is_resource($input) || ! is_resource($output)) {
                    if (is_resource($input)) {
                        fclose($input);
                    }
                    if (is_resource($output)) {
                        fclose($output);
                    }
                    throw new RuntimeException('No se pudo almacenar el fragmento.');
                }
                try {
                    if (stream_copy_to_stream($input, $output) !== $chunk->getSize()) {
                        throw new RuntimeException('Transferencia de fragmento incompleta.');
                    }
                } finally {
                    fclose($input);
                    fclose($output);
                }
            }
            $metadata['chunks'][$index] = ['sha256' => $sha256, 'size' => $chunk->getSize()];
            $upload->update(['metadata' => $metadata, 'status' => 'receiving']);

            return $upload->refresh();
        });
    }

    public function complete(MunicipalPoaUpload $upload): MunicipalPoaUpload
    {
        $shouldDispatch = false;
        $upload = $this->locked($upload->id, function () use ($upload, &$shouldDispatch): MunicipalPoaUpload {
            $upload = $upload->fresh();
            abort_unless($upload instanceof MunicipalPoaUpload, 404);
            $this->ensureAvailable($upload);
            if (in_array($upload->status, ['completed', 'queued'], true)) {
                return $upload;
            }
            $this->assertComplete($upload);
            $connection = config('queue.connections.municipal_uploads');
            abort_unless(($connection['driver'] ?? null) === 'database'
                && $connection['retry_after'] > config('municipal.poa_assembly_timeout'), 503,
                'Revise la conexión de cola y sus tiempos de espera para cargas municipales.');
            $upload->update(['status' => 'queued']);
            $shouldDispatch = true;

            return $upload->refresh();
        });
        if ($shouldDispatch) {
            try {
                CompleteMunicipalPoaUpload::dispatch($upload->id)->onConnection('municipal_uploads')->onQueue('municipal-uploads');
            } catch (Throwable $exception) {
                $this->markFailed($upload->id);
                throw $exception;
            }
        }

        return $upload;
    }

    public function process(string $id): ?MunicipalPoaDocument
    {
        if (! MunicipalPoaUpload::whereKey($id)->exists()) {
            return null;
        }

        return $this->locked($id, function () use ($id): ?MunicipalPoaDocument {
            $upload = MunicipalPoaUpload::find($id);
            if ($upload === null) {
                return null;
            }
            if ($upload->document_id !== null) {
                $this->removeParts($upload);

                return $upload->document;
            }
            $path = 'municipal/poas/'.$upload->version->municipal_poa_id.'/'.$upload->id.'.pdf';
            $disk = Storage::disk('local');
            try {
                $this->ensureAvailable($upload);
                $user = $upload->user;
                abort_unless($user !== null && $user->canAccessApi() && $user->can(PermissionName::MUNICIPAL_DOCUMENTS_UPLOAD->value), 403);
                $this->assertComplete($upload);
                $upload->update(['status' => 'processing']);
                if ($disk->exists($path)) {
                    (new CleanupMunicipalUpload($path))->handle();
                    abort_if($disk->exists($path), 409, 'El archivo definitivo ya está referenciado.');
                }
                $disk->makeDirectory(dirname($path));
                $output = fopen($disk->path($path), 'xb');
                if (! is_resource($output)) {
                    throw new RuntimeException('No se pudo crear el archivo privado definitivo.');
                }
                $hash = hash_init('sha256');
                $total = 0;
                try {
                    for ($index = 0; $index < $upload->chunkCount(); $index++) {
                        $input = $disk->readStream($this->directory($id).'/'.$index.'.part');
                        if (! is_resource($input)) {
                            throw new RuntimeException('No se pudo leer un fragmento.');
                        }
                        $chunkHash = hash_init('sha256');
                        try {
                            while (! feof($input)) {
                                $buffer = fread($input, 1024 * 1024);
                                if ($buffer === false || ($buffer === '' && ! feof($input))) {
                                    throw new RuntimeException('Lectura de fragmento incompleta.');
                                }
                                hash_update($hash, $buffer);
                                hash_update($chunkHash, $buffer);
                                $this->writeBuffer($output, $buffer);
                                $total += strlen($buffer);
                            }
                        } finally {
                            fclose($input);
                        }
                        if (! hash_equals($upload->metadata['chunks'][$index]['sha256'], hash_final($chunkHash))) {
                            throw ValidationException::withMessages(['sha256' => 'Un fragmento almacenado perdió su integridad.']);
                        }
                    }
                } finally {
                    fclose($output);
                }
                if ($total !== $upload->expected_size || ! hash_equals($upload->sha256, hash_final($hash))) {
                    throw ValidationException::withMessages(['sha256' => 'El tamaño o SHA-256 del PDF completo no coincide.']);
                }
                $document = DB::transaction(function () use ($upload, $path): MunicipalPoaDocument {
                    $document = $this->documents->register($upload->version, [...$upload->metadata['document'], 'pdf_path' => $path]);
                    if (! $upload->update(['status' => 'completed', 'document_id' => $document->id])) {
                        throw new RuntimeException('No se pudo finalizar la sesión de carga.');
                    }

                    return $document;
                }, 3);
                $this->removeParts($upload);

                return $document;
            } catch (Throwable $exception) {
                if ($upload->fresh()?->document_id === null) {
                    $upload->update(['status' => 'failed']);
                    (new CleanupMunicipalUpload($path))->handle();
                }
                throw $exception;
            }
        });
    }

    public function cancel(MunicipalPoaUpload $upload): void
    {
        $this->locked($upload->id, function () use ($upload): void {
            $upload = $upload->fresh();
            abort_unless($upload instanceof MunicipalPoaUpload, 404);
            abort_if($upload->document_id !== null, 409, 'La carga completada conserva su documento histórico.');
            $path = 'municipal/poas/'.$upload->version->municipal_poa_id.'/'.$upload->id.'.pdf';
            (new CleanupMunicipalUpload($path))->handle();
            $this->removeParts($upload);
            $upload->delete();
        });
        Storage::disk('local')->deleteDirectory($this->directory($upload->id));
    }

    public function prune(): int
    {
        $count = 0;
        foreach (MunicipalPoaUpload::query()->whereNull('document_id')->where('expires_at', '<=', now())->lazyById(100) as $upload) {
            try {
                $this->cancel($upload);
                $count++;
            } catch (HttpException $exception) {
                if ($exception->getStatusCode() !== 409) {
                    throw $exception;
                }
            }
        }

        foreach (Storage::disk('local')->directories('municipal/poa-uploads') as $directory) {
            $id = basename($directory);
            if (! Str::isUuid($id) || MunicipalPoaUpload::whereKey($id)->exists()) {
                continue;
            }
            $path = Storage::disk('local')->path($directory.'/.lock');
            if (! is_file($path) || filemtime($path) > now()->subHours((int) config('municipal.poa_upload_ttl_hours'))->timestamp) {
                continue;
            }
            try {
                $this->locked($id, static fn (): bool => true);
                Storage::disk('local')->deleteDirectory($directory);
            } catch (HttpException $exception) {
                if ($exception->getStatusCode() !== 409) {
                    throw $exception;
                }
            }
        }

        return $count;
    }

    public function markFailed(string $id): void
    {
        try {
            $this->locked($id, function () use ($id): void {
                MunicipalPoaUpload::query()->whereKey($id)->whereNull('document_id')->update(['status' => 'failed']);
            });
        } catch (HttpException $exception) {
            if ($exception->getStatusCode() !== 409) {
                throw $exception;
            }
        }
    }

    private function ensureAvailable(MunicipalPoaUpload $upload): void
    {
        abort_if($upload->document_id === null && $upload->expires_at->lte(now()), 410, 'La sesión de carga expiró.');
    }

    private function assertComplete(MunicipalPoaUpload $upload): void
    {
        $chunks = $upload->metadata['chunks'];
        for ($index = 0; $index < $upload->chunkCount(); $index++) {
            $path = $this->directory($upload->id).'/'.$index.'.part';
            if (! isset($chunks[$index]) || ! Storage::disk('local')->exists($path)
                || Storage::disk('local')->size($path) !== $upload->chunkLength($index)) {
                throw ValidationException::withMessages(['upload' => 'Faltan fragmentos completos. Consulte y reanude la sesión.']);
            }
        }
    }

    private function chunkSize(): int
    {
        $size = (int) config('municipal.poa_chunk_bytes');
        foreach (['upload_max_filesize' => 0, 'post_max_size' => 65536] as $key => $overhead) {
            $limit = ini_parse_quantity(ini_get($key));
            if ($limit > 0) {
                $size = min($size, $limit - $overhead);
            }
        }
        if ($size < 1) {
            throw new RuntimeException('Los límites de PHP impiden recibir fragmentos.');
        }

        return $size;
    }

    private function ensureCapacity(int $size): void
    {
        if ($size < 1 || $size > (int) config('municipal.poa_max_file_bytes') || (PHP_INT_SIZE < 8 && $size > 2147483647)
            || intdiv($size - 1, $this->chunkSize()) + 1 > 10000) {
            throw ValidationException::withMessages(['expected_size' => 'Tamaño no compatible con los límites configurados del servidor.']);
        }
        $disk = Storage::disk('local');
        $disk->makeDirectory('municipal/poa-uploads');
        $available = disk_free_space($disk->path('municipal/poa-uploads'));
        if ($available === false || $size > intdiv((int) $available, 2)) {
            throw ValidationException::withMessages(['expected_size' => 'Se necesita espacio libre para fragmentos y ensamblado.']);
        }
    }

    private function directory(string $id): string
    {
        if (! Str::isUuid($id)) {
            throw new RuntimeException('Identificador de carga inválido.');
        }

        return 'municipal/poa-uploads/'.$id;
    }

    private function locked(string $id, Closure $callback): mixed
    {
        $disk = Storage::disk('local');
        $directory = $this->directory($id);
        $disk->makeDirectory($directory);
        $path = $disk->path($directory.'/.lock');
        if (is_link($disk->path($directory)) || is_link($path)) {
            throw new RuntimeException('Ruta temporal inválida.');
        }
        $lock = fopen($path, 'c+b');
        if (! is_resource($lock)) {
            throw new RuntimeException('No se pudo abrir el bloqueo de la carga.');
        }
        try {
            abort_unless(flock($lock, LOCK_EX | LOCK_NB), 409, 'La carga está siendo modificada; reintente.');
            touch($path);

            return $callback();
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function removeParts(MunicipalPoaUpload $upload): void
    {
        for ($index = 0; $index < $upload->chunkCount(); $index++) {
            if (! Storage::disk('local')->delete($this->directory($upload->id).'/'.$index.'.part')) {
                throw new RuntimeException('No se pudo limpiar un fragmento temporal.');
            }
        }
    }

    /** @param resource $output */
    private function writeBuffer(mixed $output, string $buffer): void
    {
        $offset = 0;
        while ($offset < strlen($buffer)) {
            $written = fwrite($output, substr($buffer, $offset));
            if ($written === false || $written === 0) {
                throw new RuntimeException('Escritura incompleta; revise el espacio disponible.');
            }
            $offset += $written;
        }
    }
}
