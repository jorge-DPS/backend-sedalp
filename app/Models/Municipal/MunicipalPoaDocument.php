<?php

namespace App\Models\Municipal;

use App\Enums\Municipal\PoaDocumentType;
use Database\Factories\Municipal\MunicipalPoaDocumentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MunicipalPoaDocument extends Model
{
    /** @use HasFactory<MunicipalPoaDocumentFactory> */
    use HasFactory;

    protected $fillable = [
        'municipal_poa_id',
        'version_number',
        'document_type',
        'title',
        'document_reference',
        'pdf_path',
        'original_filename',
        'mime_type',
        'file_size_bytes',
        'received_at',
        'published_at',
        'is_current',
        'notes',
    ];

    protected $attributes = ['is_current' => false];

    protected $hidden = ['pdf_path'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
            'document_type' => PoaDocumentType::class,
            'file_size_bytes' => 'integer',
            'received_at' => 'immutable_date',
            'published_at' => 'immutable_datetime',
            'is_current' => 'boolean',
        ];
    }

    /** @return BelongsTo<MunicipalPoa, $this> */
    public function municipalPoa(): BelongsTo
    {
        return $this->belongsTo(MunicipalPoa::class);
    }

    /** @param Builder<MunicipalPoaDocument> $query */
    public function scopePubliclyAvailable(Builder $query): Builder
    {
        return $query->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    public function isPubliclyAvailable(): bool
    {
        return $this->published_at !== null && $this->published_at->lte(now());
    }
}
