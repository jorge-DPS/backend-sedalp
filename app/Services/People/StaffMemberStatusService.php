<?php

namespace App\Services\People;

use App\Enums\Audit\AccessStateAction;
use App\Models\Audit\AccessStateChange;
use App\Models\People\StaffMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class StaffMemberStatusService
{
    private const RELATIONS = [
        'organizationalUnit',
        'position',
        'profession',
        'user',
    ];

    public function updateStatus(
        User $actor,
        StaffMember $staffMember,
        bool $active,
        string $reason
    ): StaffMember {
        return DB::transaction(function () use (
            $actor,
            $staffMember,
            $active,
            $reason
        ): StaffMember {
            $lockedStaffMember = StaffMember::query()
                ->whereKey($staffMember->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedStaffMember->active === $active) {
                return $lockedStaffMember->load(self::RELATIONS);
            }

            $linkedUser = $lockedStaffMember
                ->user()
                ->withTrashed()
                ->lockForUpdate()
                ->first();

            if (
                ! $active
                && $linkedUser?->is($actor)
            ) {
                abort(
                    422,
                    'No puede desactivar su propio registro de personal.'
                );
            }

            if (
                $linkedUser?->hasRole('super_admin')
                && ! $actor->hasRole('super_admin')
            ) {
                abort(
                    403,
                    'Solo un superadministrador puede cambiar el estado laboral de otro superadministrador.'
                );
            }

            $lockedStaffMember->update([
                'active' => $active,
            ]);

            $linkedUser?->increment('token_version');

            AccessStateChange::create([
                'actor_user_id' => $actor->id,
                'target_user_id' => $linkedUser?->id,
                'staff_member_id' => $lockedStaffMember->id,
                'action' => $active
                    ? AccessStateAction::STAFF_ACTIVATED
                    : AccessStateAction::STAFF_DEACTIVATED,
                'reason' => $reason,
                'metadata' => [
                    'active' => $active,
                ],
            ]);

            return $lockedStaffMember
                ->refresh()
                ->load(self::RELATIONS);
        });
    }
}
