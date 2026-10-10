<?php

namespace App\Models\Territory;

use Database\Factories\Territory\RegionBoundaryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegionBoundary extends Model
{
    /** @use HasFactory<RegionBoundaryFactory> */
    use HasFactory;

    protected $fillable = ['region_id', 'source_filename', 'source_srid', 'source_properties'];

    protected $hidden = ['geom'];

    protected function casts(): array
    {
        return ['source_srid' => 'integer', 'source_properties' => 'array', 'geometry' => 'array'];
    }

    /** @return BelongsTo<Region, $this> */
    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }
}
