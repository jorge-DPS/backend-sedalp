<?php

namespace App\Models\Territory;

use Database\Factories\Territory\RegionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Region extends Model
{
    /** @use HasFactory<RegionFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'active',
    ];

    protected $attributes = [
        'active' => true,
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    /** @return HasMany<Municipality, $this> */
    public function municipalities(): HasMany
    {
        return $this->hasMany(Municipality::class);
    }

    /** @return HasOne<RegionBoundary, $this> */
    public function boundary(): HasOne
    {
        return $this->hasOne(RegionBoundary::class);
    }
}
