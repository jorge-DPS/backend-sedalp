<?php

namespace App\Models\Municipal;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MunicipalPoaUpload extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id', 'municipal_poa_version_id', 'user_id', 'idempotency_key', 'expected_size',
        'chunk_size', 'sha256', 'metadata', 'status', 'document_id', 'expires_at',
    ];

    protected $attributes = ['status' => 'receiving'];

    protected $hidden = ['metadata', 'sha256', 'idempotency_key'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['expected_size' => 'integer', 'chunk_size' => 'integer', 'metadata' => 'array', 'expires_at' => 'immutable_datetime'];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(MunicipalPoaVersion::class, 'municipal_poa_version_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(MunicipalPoaDocument::class, 'document_id');
    }

    public function chunkCount(): int
    {
        return intdiv($this->expected_size - 1, $this->chunk_size) + 1;
    }

    public function chunkLength(int $index): int
    {
        return min($this->chunk_size, $this->expected_size - $index * $this->chunk_size);
    }
}
