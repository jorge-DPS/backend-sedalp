<?php

namespace App\Services\AccessControl;

use App\Enums\Audit\AccessStateAction;
use App\Enums\Auth\UserStatus;
use App\Models\Audit\AccessStateChange;
use App\Models\People\StaffMember;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class UserService
{
    private const SUPER_ADMIN = 'super_admin';

    private const RELATIONS = [
        'staffMember.organizationalUnit',
        'staffMember.position',
        'staffMember.profession',
        'roles.permissions',
    ];

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $search = $filters['search'] ?? null;

        return User::query()
            ->with(self::RELATIONS)
            ->when(
                filled($search),
                fn (Builder $query) => $query->where(
                    function (Builder $query) use ($search): void {
                        $query
                            ->where('email', 'ILIKE', "%{$search}%")
                            ->orWhereHas(
                                'staffMember',
                                function (Builder $query) use ($search): void {
                                    $query
                                        ->where('first_names', 'ILIKE', "%{$search}%")
                                        ->orWhere('paternal_surname', 'ILIKE', "%{$search}%")
                                        ->orWhere('maternal_surname', 'ILIKE', "%{$search}%")
                                        ->orWhere('ci', 'ILIKE', "%{$search}%")
                                        ->orWhereHas(
                                            'position',
                                            fn (Builder $query) => $query->where(
                                                'name',
                                                'ILIKE',
                                                "%{$search}%"
                                            )
                                        )
                                        ->orWhereHas(
                                            'profession',
                                            fn (Builder $query) => $query->where(
                                                'name',
                                                'ILIKE',
                                                "%{$search}%"
                                            )
                                        );
                                }
                            );
                    }
                )
            )
            ->when(
                filled($filters['role'] ?? null),
                fn (Builder $query) => $query->role($filters['role'])
            )
            ->when(
                filled($filters['account_status'] ?? null),
                fn (Builder $query) => $query->where(
                    'account_status',
                    $filters['account_status']
                )
            )
            ->when(
                array_key_exists('staff_active', $filters)
                    && $filters['staff_active'] !== null,
                fn (Builder $query) => $query->whereHas(
                    'staffMember',
                    fn (Builder $query) => $query->where(
                        'active',
                        $filters['staff_active']
                    )
                )
            )
            ->when(
                filled($filters['organizational_unit_id'] ?? null),
                fn (Builder $query) => $query->whereHas(
                    'staffMember',
                    fn (Builder $query) => $query->where(
                        'organizational_unit_id',
                        $filters['organizational_unit_id']
                    )
                )
            )
            ->when(
                filled($filters['position_id'] ?? null),
                fn (Builder $query) => $query->whereHas(
                    'staffMember',
                    fn (Builder $query) => $query->where(
                        'position_id',
                        $filters['position_id']
                    )
                )
            )
            ->when(
                filled($filters['profession_id'] ?? null),
                fn (Builder $query) => $query->whereHas(
                    'staffMember',
                    fn (Builder $query) => $query->where(
                        'profession_id',
                        $filters['profession_id']
                    )
                )
            )
            ->latest('id')
            ->paginate((int) ($filters['per_page'] ?? 15))
            ->withQueryString();
    }

    public function create(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $staffMemberId = $data['staff_member_id'] ?? null;

            if (isset($data['staff_member'])) {
                $staffMemberId = StaffMember::create(
                    $data['staff_member']
                )->id;
            }

            $user = User::create([
                'staff_member_id' => $staffMemberId,
                'email' => $data['email'],
                'password' => $data['password'],
            ]);

            $user->syncRoles([
                $data['role'],
            ]);

            return $user->load(self::RELATIONS);
        });
    }

    public function update(
        User $actor,
        User $user,
        array $data
    ): User {
        return DB::transaction(function () use (
            $actor,
            $user,
            $data
        ): User {
            $lockedUser = User::query()
                ->whereKey($user->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->ensureCanModifyUser(
                actor: $actor,
                target: $lockedUser,
            );

            $staffData = $data['staff_member'] ?? null;

            unset($data['staff_member']);

            if ($staffData !== null) {
                $this->updateStaffMember(
                    user: $lockedUser,
                    data: $staffData,
                );
            }

            $passwordChanged = array_key_exists(
                'password',
                $data
            );

            $lockedUser->update($data);

            if ($passwordChanged) {
                $lockedUser->increment('token_version');

                $this->recordChange(
                    actor: $actor,
                    target: $lockedUser,
                    action: AccessStateAction::USER_CREDENTIALS_UPDATED,
                    reason: 'Actualización de contraseña de la cuenta.',
                    metadata: [
                        'fields' => ['password'],
                    ],
                );
            }

            return $lockedUser->load(self::RELATIONS);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function updateStaffMember(
        User $user,
        array $data
    ): void {
        if ($user->staff_member_id === null) {
            $staffMember = StaffMember::create($data);

            $user->staff_member_id = $staffMember->id;

            return;
        }

        $staffMember = StaffMember::withTrashed()
            ->whereKey($user->staff_member_id)
            ->lockForUpdate()
            ->firstOrFail();

        abort_if(
            $staffMember->trashed(),
            422,
            'Debe restaurar primero al personal asociado.'
        );

        $staffMember->update($data);
    }

    public function updateRole(
        User $actor,
        User $user,
        string $role
    ): User {
        $this->ensureCanChangeRole(
            actor: $actor,
            target: $user,
            newRole: $role,
        );

        return DB::transaction(function () use (
            $user,
            $role
        ) {
            $user->syncRoles([
                $role,
            ]);

            return $user->load(self::RELATIONS);
        });
    }

    public function load(User $user): User
    {
        return $user->load(self::RELATIONS);
    }

    public function updateStatus(
        User $actor,
        User $user,
        UserStatus $status,
        string $reason
    ): User {
        return DB::transaction(function () use (
            $actor,
            $user,
            $status,
            $reason
        ): User {
            $lockedUser = User::query()
                ->whereKey($user->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->ensureCanChangeStatus(
                actor: $actor,
                target: $lockedUser,
                status: $status,
            );

            if ($lockedUser->account_status === $status) {
                return $lockedUser->load(self::RELATIONS);
            }

            if ($status === UserStatus::ACTIVE) {
                $this->ensureStaffAllowsActivation($lockedUser);
            }

            $previousStatus = $lockedUser->account_status;

            $lockedUser->update([
                'account_status' => $status,
            ]);

            $lockedUser->increment('token_version');

            $this->recordChange(
                actor: $actor,
                target: $lockedUser,
                action: $status === UserStatus::ACTIVE
                    ? AccessStateAction::USER_ACTIVATED
                    : AccessStateAction::USER_SUSPENDED,
                reason: $reason,
                metadata: [
                    'from' => $previousStatus->value,
                    'to' => $status->value,
                ],
            );

            return $lockedUser->load(self::RELATIONS);
        });
    }

    public function delete(
        User $actor,
        User $user,
        string $reason
    ): void {
        DB::transaction(function () use (
            $actor,
            $user,
            $reason
        ): void {
            $lockedUser = User::query()
                ->whereKey($user->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->ensureCanDeleteUser(
                actor: $actor,
                target: $lockedUser,
            );

            $lockedUser->increment('token_version');

            $this->recordChange(
                actor: $actor,
                target: $lockedUser,
                action: AccessStateAction::USER_DELETED,
                reason: $reason,
            );

            $lockedUser->delete();
        });
    }

    public function restore(
        User $actor,
        User $user,
        string $reason
    ): User {
        return DB::transaction(function () use (
            $actor,
            $user,
            $reason
        ): User {
            $lockedUser = User::withTrashed()
                ->whereKey($user->id)
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless(
                $lockedUser->trashed(),
                404
            );

            $this->ensureStaffAllowsActivation($lockedUser);

            $lockedUser->restore();
            $lockedUser->increment('token_version');

            $this->recordChange(
                actor: $actor,
                target: $lockedUser,
                action: AccessStateAction::USER_RESTORED,
                reason: $reason,
            );

            return $lockedUser->load(self::RELATIONS);
        });
    }

    private function ensureCanModifyUser(
        User $actor,
        User $target
    ): void {
        /*
         * Un usuario normal no puede modificar
         * las credenciales de un superadministrador.
         *
         * Un superadministrador sí puede modificar
         * a otro superadministrador o a sí mismo.
         */
        if (
            $target->hasRole(self::SUPER_ADMIN)
            && ! $actor->hasRole(self::SUPER_ADMIN)
        ) {
            abort(
                403,
                'No puede modificar las credenciales de un superadministrador.'
            );
        }
    }

    private function ensureCanChangeRole(
        User $actor,
        User $target,
        string $newRole
    ): void {
        $targetIsSuperAdmin = $target->hasRole(
            self::SUPER_ADMIN
        );

        $actorIsSuperAdmin = $actor->hasRole(
            self::SUPER_ADMIN
        );

        /*
         * Solo un superadministrador puede:
         *
         * - asignar super_admin;
         * - modificar el rol de otro super_admin.
         */
        if (
            (
                $targetIsSuperAdmin
                || $newRole === self::SUPER_ADMIN
            )
            && ! $actorIsSuperAdmin
        ) {
            abort(
                403,
                'Solo un superadministrador puede administrar el rol super_admin.'
            );
        }

        /*
         * Un superadministrador nunca puede
         * quitarse su propio rol privilegiado.
         *
         * Esto evita dejar accidentalmente
         * el sistema sin administración.
         */
        if (
            $actor->is($target)
            && $targetIsSuperAdmin
            && $newRole !== self::SUPER_ADMIN
        ) {
            abort(
                422,
                'No puede quitarse su propio rol de superadministrador.'
            );
        }
    }

    private function ensureCanChangeStatus(
        User $actor,
        User $target,
        UserStatus $status
    ): void {
        if (
            $actor->is($target)
            && $status === UserStatus::SUSPENDED
        ) {
            abort(
                422,
                'No puede suspender su propia cuenta.'
            );
        }

        if (
            $target->hasRole(self::SUPER_ADMIN)
            && ! $actor->hasRole(self::SUPER_ADMIN)
        ) {
            abort(
                403,
                'Solo un superadministrador puede cambiar el estado de otro superadministrador.'
            );
        }
    }

    private function ensureCanDeleteUser(
        User $actor,
        User $target
    ): void {
        if ($actor->is($target)) {
            abort(
                422,
                'No puede eliminar su propia cuenta.'
            );
        }

        if (
            $target->hasRole(self::SUPER_ADMIN)
            && ! $actor->hasRole(self::SUPER_ADMIN)
        ) {
            abort(
                403,
                'No está autorizado para eliminar un superadministrador.'
            );
        }
    }

    private function ensureStaffAllowsActivation(
        User $user
    ): void {
        if ($user->staff_member_id === null) {
            return;
        }

        $staffMember = $user->staffMember()
            ->withTrashed()
            ->first();

        if (
            $staffMember === null
            || $staffMember->trashed()
            || ! $staffMember->active
        ) {
            abort(
                422,
                'Debe reactivar primero al personal asociado.'
            );
        }
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    private function recordChange(
        User $actor,
        User $target,
        AccessStateAction $action,
        string $reason,
        ?array $metadata = null
    ): void {
        AccessStateChange::create([
            'actor_user_id' => $actor->id,
            'target_user_id' => $target->id,
            'staff_member_id' => $target->staff_member_id,
            'action' => $action,
            'reason' => $reason,
            'metadata' => $metadata,
        ]);
    }
}
