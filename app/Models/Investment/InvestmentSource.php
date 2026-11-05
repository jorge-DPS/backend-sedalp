<?php

namespace App\Models\Investment;

use Database\Factories\Investment\InvestmentSourceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InvestmentSource extends Model
{
    /** @use HasFactory<InvestmentSourceFactory> */
    use HasFactory;

    protected $fillable = [
        'institution_id',
        'provided_by_name',
        'source_type',
        'title',
        'fiscal_year',
        'document_reference',
        'version_label',
        'file_path',
        'source_url',
        'received_at',
        'notes',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'fiscal_year' => 'integer',
            'received_at' => 'immutable_date',
        ];
    }

    /** @return BelongsTo<Institution, $this> */
    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class)->withTrashed();
    }

    /** @return HasMany<Project, $this> */
    public function primaryProjects(): HasMany
    {
        return $this->hasMany(Project::class, 'primary_investment_source_id');
    }

    /** @return HasMany<ProjectBudget, $this> */
    public function projectBudgets(): HasMany
    {
        return $this->hasMany(ProjectBudget::class);
    }

    /** @return HasMany<ProjectBudgetRevision, $this> */
    public function budgetRevisions(): HasMany
    {
        return $this->hasMany(ProjectBudgetRevision::class);
    }

    /** @return HasMany<ProjectBudgetRevision, $this> */
    public function previousBudgetRevisions(): HasMany
    {
        return $this->hasMany(ProjectBudgetRevision::class, 'previous_investment_source_id');
    }
}
