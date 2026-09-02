<?php

namespace App\Enums\Auth;

enum AccessStatus: string
{
    case ACTIVE = 'active';
    case SUSPENDED = 'suspended';
    case DISABLED_BY_STAFF = 'disabled_by_staff';
    case DELETED = 'deleted';
}
