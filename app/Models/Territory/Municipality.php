<?php

namespace App\Models\Territory;

use App\Models\Investment\Project;
use App\Models\Municipal\MunicipalAuthority;
use App\Models\Municipal\MunicipalPoa;
use Database\Factories\Territory\MunicipalityFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Municipality extends Model
{
    /** @use HasFactory<MunicipalityFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'region_id',
        'province_id',
        'name',
        'official_code',
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

    /** @return BelongsTo<Region, $this> */
    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    /** @return BelongsTo<Province, $this> */
    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    /** @return HasMany<MunicipalityRegionMembership, $this> */
    public function regionMemberships(): HasMany
    {
        return $this->hasMany(MunicipalityRegionMembership::class);
    }

    /** @return BelongsToMany<Project, $this> */
    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_municipalities')->withTimestamps();
    }

    /** @return HasMany<MunicipalPoa, $this> */
    public function municipalPoas(): HasMany
    {
        return $this->hasMany(MunicipalPoa::class);
    }

    /** @return HasMany<MunicipalAuthority, $this> */
    public function municipalAuthorities(): HasMany
    {
        return $this->hasMany(MunicipalAuthority::class);
    }

    /** @param Builder<Municipality> $query */
    public function scopePubliclyAvailable(Builder $query): Builder
    {
        return $query->where('active', true)
            ->whereHas('region', fn (Builder $query): Builder => $query->where('active', true))
            ->whereHas('province', fn (Builder $query): Builder => $query->where('active', true));
    }
}
