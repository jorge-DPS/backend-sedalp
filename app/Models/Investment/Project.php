<?php

namespace App\Models\Investment;

use App\Models\Territory\Municipality;
use App\Models\Territory\Province;
use Database\Factories\Investment\ProjectFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory, SoftDeletes;

    /** Nuevo o de continuidad, únicamente cuando se conoce la condición. */
    public const CONDITION_NEW = 'new';

    public const CONDITION_CONTINUING = 'continuing';

    /** Condición declarada por la fuente o inferida durante el procesamiento. */
    public const CONDITION_ORIGIN_DECLARED = 'declared';

    public const CONDITION_ORIGIN_INFERRED = 'inferred';

    protected $fillable = [
        'institution_id',
        'sector_id',
        'strategic_axis_id',
        'intervention_type_id',
        'project_status_id',
        'main_province_id',
        'primary_investment_source_id',
        'source_code',
        'name',
        'short_name',
        'executing_unit',
        'cycle_phase',
        'project_condition',
        'condition_origin',
        'original_status',
        'territorial_scope',
        'total_cost_bob',
        'funding_source',
        'source_reference',
        'source_year',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'total_cost_bob' => 'decimal:2',
            'source_year' => 'integer',
        ];
    }

    /** @return BelongsTo<Institution, $this> */
    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    /** @return BelongsTo<Sector, $this> */
    public function sector(): BelongsTo
    {
        return $this->belongsTo(Sector::class);
    }

    /** @return BelongsTo<StrategicAxis, $this> */
    public function strategicAxis(): BelongsTo
    {
        return $this->belongsTo(StrategicAxis::class);
    }

    /** @return BelongsTo<InterventionType, $this> */
    public function interventionType(): BelongsTo
    {
        return $this->belongsTo(InterventionType::class);
    }

    /** @return BelongsTo<ProjectStatus, $this> */
    public function projectStatus(): BelongsTo
    {
        return $this->belongsTo(ProjectStatus::class);
    }

    /** @return BelongsTo<Province, $this> */
    public function mainProvince(): BelongsTo
    {
        return $this->belongsTo(Province::class, 'main_province_id');
    }

    /** @return BelongsTo<InvestmentSource, $this> */
    public function primaryInvestmentSource(): BelongsTo
    {
        return $this->belongsTo(InvestmentSource::class, 'primary_investment_source_id');
    }

    /** @return HasMany<ProjectBudget, $this> */
    public function budgets(): HasMany
    {
        return $this->hasMany(ProjectBudget::class);
    }

    /** @return BelongsToMany<Province, $this> */
    public function provinces(): BelongsToMany
    {
        return $this->belongsToMany(Province::class, 'project_provinces')->withTimestamps();
    }

    /** @return BelongsToMany<Municipality, $this> */
    public function municipalities(): BelongsToMany
    {
        return $this->belongsToMany(Municipality::class, 'project_municipalities')->withTimestamps();
    }

    /**
     * @param  Builder<Project>  $query
     * @return Builder<Project>
     */
    public function scopeInProvince(Builder $query, int $provinceId): Builder
    {
        return $query->whereHas('provinces', fn (Builder $provinces): Builder => $provinces->whereKey($provinceId));
    }

    /**
     * @param  Builder<Project>  $query
     * @return Builder<Project>
     */
    public function scopeInMunicipality(Builder $query, int $municipalityId): Builder
    {
        return $query->whereHas('municipalities', fn (Builder $municipalities): Builder => $municipalities->whereKey($municipalityId));
    }

    /**
     * @param  Builder<Project>  $query
     * @return Builder<Project>
     */
    public function scopeInCurrentRegion(Builder $query, int $regionId): Builder
    {
        return $query->whereHas('municipalities', fn (Builder $municipalities): Builder => $municipalities->where('region_id', $regionId));
    }

    /**
     * @param  Builder<Project>  $query
     * @return Builder<Project>
     */
    public function scopeInRegionOn(Builder $query, int $regionId, string $date): Builder
    {
        return $query->whereHas('municipalities.regionMemberships', fn (Builder $memberships): Builder => $memberships
            ->where('region_id', $regionId)->validOn($date));
    }
}
