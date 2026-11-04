<?php

namespace App\Models\Investment;

use App\Enums\Investment\BudgetAvailabilityStatus;
use Database\Factories\Investment\ProjectBudgetRevisionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectBudgetRevision extends Model
{
    /** @use HasFactory<ProjectBudgetRevisionFactory> */
    use HasFactory;

    protected $fillable = [
        'project_budget_id',
        'revision_number',
        'previous_amount_bob',
        'new_amount_bob',
        'previous_availability_status',
        'new_availability_status',
        'investment_source_id',
        'source_reference',
        'effective_date',
        'reason',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'revision_number' => 'integer',
            'previous_amount_bob' => 'decimal:2',
            'new_amount_bob' => 'decimal:2',
            'previous_availability_status' => BudgetAvailabilityStatus::class,
            'new_availability_status' => BudgetAvailabilityStatus::class,
            'effective_date' => 'immutable_date',
        ];
    }

    /** @return BelongsTo<ProjectBudget, $this> */
    public function projectBudget(): BelongsTo
    {
        return $this->belongsTo(ProjectBudget::class);
    }

    /** @return BelongsTo<InvestmentSource, $this> */
    public function investmentSource(): BelongsTo
    {
        return $this->belongsTo(InvestmentSource::class);
    }
}
