<?php

namespace App\Http\Controllers\Api\Admin\AccessControl;

use App\Http\Controllers\Controller;
use App\Http\Requests\AccessControl\StoreUserRequest;
use App\Http\Requests\AccessControl\UpdateUserAccessRequest;
use App\Http\Requests\AccessControl\UpdateUserRequest;
use App\Http\Resources\AccessControl\UserResource;
use App\Models\User;
use App\Services\AccessControl\UserService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class UserController extends Controller
{
    public function __construct(
        private readonly UserService $userService
    ) {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = min(
            max($request->integer('per_page', 15), 1),
            100
        );

        $search = trim(
            (string) $request->query('search', '')
        );

        $users = User::query()
            ->with([
                'staffMember.organizationalUnit',
                'staffMember.position',
                'staffMember.profession',
                'roles.permissions',
                'permissions',
            ])
            ->when(
                $search !== '',
                fn ($query) => $query->where(
                    'email',
                    'ILIKE',
                    "%{$search}%"
                )
            )
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();

        return UserResource::collection($users);
    }

    public function store(StoreUserRequest $request): UserResource
    {
        $user = $this->userService->create(
            $request->validated()
        );

        return new UserResource($user);
    }

    public function show(User $user): UserResource
    {
        return new UserResource(
            $this->userService->load($user)
        );
    }

    public function update(
        UpdateUserRequest $request,
        User $user
    ): UserResource {
        $user = $this->userService->update(
            $user,
            $request->validated()
        );

        return new UserResource($user);
    }

    public function updateAccess(
        UpdateUserAccessRequest $request,
        User $user
    ): UserResource {
        $user = $this->userService->updateAccess(
            $user,
            $request->validated()
        );

        return new UserResource($user);
    }

    public function destroy(
        Request $request,
        User $user
    ): Response {
        $authenticatedUser = $request->user('api');

        if ($authenticatedUser->is($user)) {
            abort(
                422,
                'No puede eliminar su propia cuenta.'
            );
        }

        if (
            $user->hasRole('super_admin')
            && ! $authenticatedUser->hasRole('super_admin')
        ) {
            abort(
                403,
                'No está autorizado para eliminar un superadministrador.'
            );
        }

        $user->delete();

        return response()->noContent();
    }
}
