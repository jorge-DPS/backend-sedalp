<?php

namespace App\Models\Territory;

use Database\Factories\Territory\MapLayerFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MapLayer extends Model
{
    /** @use HasFactory<MapLayerFactory> */
    use HasFactory;

    protected $fillable = ['name', 'description', 'region_id', 'kind', 'category'];

    protected $attributes = ['kind' => 'overlay', 'category' => 'other'];

    protected function casts(): array
    {
        return ['published_at' => 'immutable_datetime', 'published_version_id' => 'integer'];
    }

    public function publishedVersion(): BelongsTo
    {
        return $this->belongsTo(MapLayerVersion::class, 'published_version_id');
    }

    /** @param Builder<MapLayer> $query */
    public function scopePubliclyVisible(Builder $query): void
    {
        $query->whereNotNull('published_version_id')->whereHas('publishedVersion')
            ->where(fn (Builder $regions): Builder => $regions->whereNull('region_id')->orWhereHas('region'));
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(MapLayerVersion::class);
    }

    public function currentVersion(): HasOne
    {
        return $this->hasOne(MapLayerVersion::class)->ofMany('number', 'max');
    }
}
