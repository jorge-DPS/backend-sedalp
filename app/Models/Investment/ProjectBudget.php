<?php

namespace App\Models\Investment;

use App\Enums\Investment\BudgetAvailabilityStatus;
use Database\Factories\Investment\ProjectBudgetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectBudget extends Model
{
    /** @use HasFactory<ProjectBudgetFactory> */
    use HasFactory;

    protected $fillable = [
        'project_id',
        'fiscal_year',
        'amount_bob',
        'availability_status',
        'investment_source_id',
        'source_reference',
        'funding_source',
        'verified_at',
        'notes',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'fiscal_year' => 'integer',
            'amount_bob' => 'decimal:2',
            'availability_status' => BudgetAvailabilityStatus::class,
            'verified_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class)->withTrashed();
    }

    /** @return BelongsTo<InvestmentSource, $this> */
    public function investmentSource(): BelongsTo
    {
        return $this->belongsTo(InvestmentSource::class);
    }

    /** @return HasMany<ProjectBudgetRevision, $this> */
    public function revisions(): HasMany
    {
        return $this->hasMany(ProjectBudgetRevision::class);
    }
}
