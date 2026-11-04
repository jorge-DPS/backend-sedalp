<?php

namespace App\Enums\Investment;

enum BudgetAvailabilityStatus: string
{
    case REPORTED = 'reported';
    case NOT_AVAILABLE = 'not_available';
}
