<?php

namespace App\Models\Municipal;

use App\Enums\Municipal\PoaDocumentType;
use Database\Factories\Municipal\MunicipalPoaDocumentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

class MunicipalPoaDocument extends Model
{
    /** @use HasFactory<MunicipalPoaDocumentFactory> */
    use HasFactory;

    protected $fillable = ['municipal_poa_version_id', 'title', 'document_reference', 'pdf_path', 'original_filename', 'mime_type', 'file_size_bytes', 'received_at', 'notes'];

    protected $hidden = ['pdf_path'];

    protected $with = ['version'];

    protected function casts(): array
    {
        return ['file_size_bytes' => 'integer', 'received_at' => 'immutable_date'];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(MunicipalPoaVersion::class, 'municipal_poa_version_id');
    }

    public function municipalPoa(): HasOneThrough
    {
        return $this->hasOneThrough(MunicipalPoa::class, MunicipalPoaVersion::class, 'id', 'id', 'municipal_poa_version_id', 'municipal_poa_id');
    }

    protected function municipalPoaId(): Attribute
    {
        return Attribute::get(fn (): int => $this->version->municipal_poa_id);
    }

    protected function versionNumber(): Attribute
    {
        return Attribute::get(fn (): int => $this->version->version_number);
    }

    protected function documentType(): Attribute
    {
        return Attribute::get(fn (): PoaDocumentType => $this->version->document_type);
    }

    protected function publishedAt(): Attribute
    {
        return Attribute::get(fn () => $this->version->published_at);
    }

    protected function isCurrent(): Attribute
    {
        return Attribute::get(fn (): bool => $this->version->is_current);
    }

    public function scopePubliclyAvailable(Builder $query): Builder
    {
        return $query->whereHas('version', fn (Builder $query): Builder => $query->publiclyAvailable());
    }

    public function isPubliclyAvailable(): bool
    {
        return $this->version->isPubliclyAvailable();
    }
}
