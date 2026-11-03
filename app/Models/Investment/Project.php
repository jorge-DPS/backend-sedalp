<?php

namespace App\Models\Investment;

use App\Models\Territory\Province;
use Database\Factories\Investment\ProjectFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
}
