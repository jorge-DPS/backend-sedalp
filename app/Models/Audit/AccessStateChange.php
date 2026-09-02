<?php

namespace App\Models\Audit;

use App\Enums\Audit\AccessStateAction;
use App\Models\People\StaffMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccessStateChange extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'actor_user_id',
        'target_user_id',
        'staff_member_id',
        'action',
        'reason',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'action' => AccessStateAction::class,
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'actor_user_id'
        );
    }

    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'target_user_id'
        )->withTrashed();
    }

    public function staffMember(): BelongsTo
    {
        return $this->belongsTo(StaffMember::class)
            ->withTrashed();
    }
}
