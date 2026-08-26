<?php

namespace App\Http\Controllers\Api\Admin\AccessControl;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AccessCatalogController extends Controller
{
    public function roles(Request $request): JsonResponse
    {
        $query = Role::query()
            ->where('guard_name', 'api')
            ->with([
                'permissions:id,name',
            ])
            ->orderBy('name');

        if (! $request->user('api')->hasRole('super_admin')) {
            $query->where('name', '!=', 'super_admin');
        }

        $roles = $query->get()
            ->map(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,

                'permissions' => $role->permissions
                    ->pluck('name')
                    ->sort()
                    ->values(),
            ]);

        return response()->json([
            'data' => $roles,
        ]);
    }

    public function permissions(): JsonResponse
    {
        $permissions = Permission::query()
            ->where('guard_name', 'api')
            ->orderBy('name')
            ->get([
                'id',
                'name',
            ]);

        return response()->json([
            'data' => $permissions,
        ]);
    }
}
