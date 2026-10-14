<?php

namespace App\Models\Territory;

use Database\Factories\Territory\MapFeatureFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MapFeature extends Model
{
    /** @use HasFactory<MapFeatureFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['position', 'properties'];

    protected $hidden = ['geom'];

    protected function casts(): array
    {
        return ['properties' => 'array', 'geometry' => 'array'];
    }

    public function mapLayerVersion(): BelongsTo
    {
        return $this->belongsTo(MapLayerVersion::class);
    }
}
