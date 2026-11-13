<?php

namespace App\Models\Municipal;

use App\Enums\Municipal\PoaDocumentType;
use Database\Factories\Municipal\MunicipalPoaVersionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MunicipalPoaVersion extends Model
{
    /** @use HasFactory<MunicipalPoaVersionFactory> */
    use HasFactory;

    protected $fillable = ['municipal_poa_id', 'version_number', 'document_type', 'published_at', 'is_current'];

    protected $attributes = ['is_current' => false];

    protected function casts(): array
    {
        return ['version_number' => 'integer', 'document_type' => PoaDocumentType::class, 'published_at' => 'immutable_datetime', 'is_current' => 'boolean'];
    }

    public function municipalPoa(): BelongsTo
    {
        return $this->belongsTo(MunicipalPoa::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(MunicipalPoaDocument::class);
    }

    public function uploads(): HasMany
    {
        return $this->hasMany(MunicipalPoaUpload::class);
    }

    public function scopePubliclyAvailable(Builder $query): Builder
    {
        return $query->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    public function isPubliclyAvailable(): bool
    {
        return $this->published_at !== null && $this->published_at->lte(now());
    }

    /** @param array<string, mixed> $filters */
    public function scopeFiltered(Builder $query, array $filters): Builder
    {
        foreach (['document_type', 'is_current'] as $field) {
            if (array_key_exists($field, $filters)) {
                $query->where($field, $filters[$field]);
            }
        }
        if (array_key_exists('published', $filters)) {
            $filters['published'] ? $query->publiclyAvailable()
                : $query->where(fn (Builder $query): Builder => $query->whereNull('published_at')->orWhere('published_at', '>', now()));
        }

        return $query;
    }
}
