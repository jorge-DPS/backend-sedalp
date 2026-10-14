<?php

namespace App\Models\Territory;

use Database\Factories\Territory\MapLayerVersionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MapLayerVersion extends Model
{
    /** @use HasFactory<MapLayerVersionFactory> */
    use HasFactory;

    protected $fillable = ['number', 'uploaded_by', 'source_filename', 'source_path', 'sha256', 'summary'];

    protected $hidden = ['source_path'];

    protected function casts(): array
    {
        return ['summary' => 'array', 'number' => 'integer'];
    }

    public function mapLayer(): BelongsTo
    {
        return $this->belongsTo(MapLayer::class);
    }

    public function features(): HasMany
    {
        return $this->hasMany(MapFeature::class)->orderBy('position');
    }
}
