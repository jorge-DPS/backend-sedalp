<?php

namespace App\Http\Controllers\Api\Admin\AccessControl;

use App\Http\Controllers\Controller;
use App\Models\People\OrganizationalUnit;
use App\Models\People\Position;
use App\Models\People\Profession;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class UserCatalogController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user('api');
        abort_unless($user->can('users.view') || $user->can('users.create') || $user->can('users.update'), 403);
        $roles = $user->can('roles.assign') || $user->can('roles.view')
        ? Role::query()->where('guard_name', 'api')->when(! $user->hasRole('super_admin'), fn (Builder $query): Builder => $query->where('name', '!=', 'super_admin'))->with('permissions:id,name')->orderBy('name')->get()->map(fn (Role $role): array => ['id' => $role->id, 'name' => $role->name, 'permissions' => $role->permissions->pluck('name')->values()])
        : [];

        return response()->json(['data' => [
            'roles' => $roles,
            'organizational_units' => OrganizationalUnit::query()->where('active', true)->orderBy('name')->get(['id', 'name', 'code', 'description', 'active']),
            'positions' => Position::query()->where('active', true)->orderBy('name')->get(['id', 'name', 'description', 'active']),
            'professions' => Profession::query()->where('active', true)->orderBy('name')->get(['id', 'name', 'active']),
        ]]);
    }
}
