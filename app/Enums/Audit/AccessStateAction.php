<?php

namespace App\Enums\Audit;

enum AccessStateAction: string
{
    case USER_ACTIVATED = 'user_activated';
    case USER_SUSPENDED = 'user_suspended';
    case USER_DELETED = 'user_deleted';
    case USER_RESTORED = 'user_restored';
    case USER_CREDENTIALS_UPDATED = 'user_credentials_updated';
    case STAFF_ACTIVATED = 'staff_activated';
    case STAFF_DEACTIVATED = 'staff_deactivated';
}
