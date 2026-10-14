<?php

namespace App\Models\Territory;

use Database\Factories\Territory\MapLayerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MapLayer extends Model
{
    /** @use HasFactory<MapLayerFactory> */
    use HasFactory;

    protected $fillable = ['name', 'description', 'region_id', 'kind'];

    protected $attributes = ['kind' => 'overlay'];

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
