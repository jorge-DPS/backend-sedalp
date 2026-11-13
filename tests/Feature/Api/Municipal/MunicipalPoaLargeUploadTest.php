<?php

use App\Jobs\Municipal\CompleteMunicipalPoaUpload;
use App\Models\Municipal\MunicipalPoaDocument;
use App\Models\Municipal\MunicipalPoaVersion;
use App\Models\User;
use App\Services\Municipal\MunicipalPoaUploadService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

it('transfiere un PDF real superior a dos GiB con memoria limitada y verifica su hash completo', function (): void {
    expect(PHP_INT_SIZE)->toBe(8)->and(ini_get('memory_limit'))->toBe('256M');
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('local');
    Queue::fake([CompleteMunicipalPoaUpload::class]);
    config(['municipal.poa_chunk_bytes' => 8388608]);
    $user = User::factory()->create();
    $user->assignRole('tecnico');
    $this->actingAs($user, 'api');
    $version = MunicipalPoaVersion::factory()->create();
    $url = '/api/admin/municipalities/'.$version->municipalPoa->municipality_id.'/poas/'.$version->municipal_poa_id.'/versions/'.$version->id.'/uploads';
    $fixture = tempnam(sys_get_temp_dir(), 'simred-large-pdf-');
    $chunkPath = null;
    $input = null;
    try {
        $length = 2147483648;
        $prefix = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            ."2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            ."3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 100 100] /Contents 4 0 R >>\nendobj\n"
            ."4 0 obj\n<< /Length ".$length." >>\nstream\n";
        $ending = "\nendstream\nendobj\n";
        $xref = "xref\n0 5\n0000000000 65535 f \n";
        foreach ([1, 2, 3, 4] as $number) {
            $xref .= sprintf("%010d 00000 n \n", strpos($prefix, $number.' 0 obj'));
        }
        $tail = $ending.$xref."trailer\n<< /Size 5 /Root 1 0 R >>\nstartxref\n".(strlen($prefix) + $length + strlen($ending))."\n%%EOF\n";
        $output = fopen($fixture, 'wb');
        try {
            fwrite($output, $prefix);
            $buffer = str_repeat(' ', 1024 * 1024);
            for ($remaining = $length; $remaining > 0; $remaining -= strlen($buffer)) {
                expect(fwrite($output, $buffer))->toBe(strlen($buffer));
            }
            fwrite($output, $tail);
        } finally {
            fclose($output);
        }
        clearstatcache(true, $fixture);
        $size = filesize($fixture);
        $sha = hash_file('sha256', $fixture);
        expect($size)->toBeGreaterThan(2147483648);
        $response = $this->postJson($url, [
            'idempotency_key' => (string) Str::uuid(), 'title' => 'PDF de prueba de capacidad',
            'original_filename' => 'capacidad.pdf', 'expected_size' => $size, 'sha256' => $sha,
        ])->assertCreated();
        $id = $response->json('data.id');
        $input = fopen($fixture, 'rb');
        for ($index = 0; $index < $response->json('data.total_chunks'); $index++) {
            $chunkPath = tempnam(sys_get_temp_dir(), 'simred-pdf-chunk-');
            $output = fopen($chunkPath, 'wb');
            try {
                $copied = stream_copy_to_stream($input, $output, $response->json('data.chunk_size'));
            } finally {
                fclose($output);
            }
            expect($copied)->toBeGreaterThan(0);
            $this->putJson($url.'/'.$id.'/chunks/'.$index, [
                'chunk' => new UploadedFile($chunkPath, 'fragment.bin', 'application/octet-stream', UPLOAD_ERR_OK, true),
                'sha256' => hash_file('sha256', $chunkPath),
            ])->assertOk();
            unlink($chunkPath);
            $chunkPath = null;
        }
        $this->postJson($url.'/'.$id.'/complete')->assertAccepted();
        (new CompleteMunicipalPoaUpload($id))->handle(app(MunicipalPoaUploadService::class));
        $document = MunicipalPoaDocument::sole();
        expect($document->file_size_bytes)->toBe($size)
            ->and(hash_file('sha256', Storage::disk('local')->path($document->pdf_path)))->toBe($sha)
            ->and(memory_get_peak_usage(true))->toBeLessThanOrEqual(256 * 1024 * 1024);
    } finally {
        if (is_resource($input)) {
            fclose($input);
        }
        if ($chunkPath !== null && is_file($chunkPath)) {
            unlink($chunkPath);
        }
        if (is_file($fixture)) {
            unlink($fixture);
        }
        foreach (Storage::disk('local')->allFiles('municipal/poas') as $path) {
            Storage::disk('local')->delete($path);
        }
        if (isset($id)) {
            Storage::disk('local')->deleteDirectory('municipal/poa-uploads/'.$id);
        }
    }
})->skip(fn (): bool => getenv('SIMRED_RUN_LARGE_UPLOAD_TEST') !== '1',
    'Prueba de capacidad ejecutable por separado con SIMRED_RUN_LARGE_UPLOAD_TEST=1 y PHP memory_limit=256M.');
