<?php

namespace App\Http\Requests\Investment;

use App\Enums\Auth\PermissionName;
use App\Enums\Investment\BudgetAvailabilityStatus;
use App\Models\Investment\InvestmentSource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectBudgetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('api')?->can(PermissionName::INVESTMENT_BUDGETS_CREATE->value) ?? false;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'fiscal_year' => ['required', 'integer', 'between:1900,2100'],
            'amount_bob' => ['present', 'nullable', 'string', 'regex:/^[0-9]{1,16}(?:\\.[0-9]{1,2})?$/D'],
            'availability_status' => ['required', Rule::enum(BudgetAvailabilityStatus::class)],
            'investment_source_id' => ['sometimes', 'nullable', 'integer', Rule::exists(InvestmentSource::class, 'id')],
            'source_reference' => ['sometimes', 'nullable', 'string'],
            'funding_source' => ['sometimes', 'nullable', 'string', 'max:255'],
            'verified_at' => ['sometimes', 'nullable', 'date'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_int($this->input('amount_bob'))) {
            $this->merge(['amount_bob' => (string) $this->input('amount_bob')]);
        }
    }
}
