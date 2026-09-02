<?php

namespace App\Http\Controllers\Api\Admin\AccessControl;

use App\Http\Controllers\Controller;
use App\Http\Requests\AccessControl\IndexUserTrashRequest;
use App\Http\Requests\AccessControl\RestoreUserRequest;
use App\Http\Resources\AccessControl\UserResource;
use App\Models\User;
use App\Services\AccessControl\UserService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserTrashController extends Controller
{
    private const RELATIONS = [
        'staffMember.organizationalUnit',
        'staffMember.position',
        'staffMember.profession',
        'roles.permissions',
    ];

    public function __construct(
        private readonly UserService $userService
    ) {}

    public function index(
        IndexUserTrashRequest $request
    ): AnonymousResourceCollection {
        $filters = $request->validated();

        $users = User::onlyTrashed()
            ->with(self::RELATIONS)
            ->when(
                filled($filters['search'] ?? null),
                fn ($query) => $query->where(
                    'email',
                    'ILIKE',
                    "%{$filters['search']}%"
                )
            )
            ->orderByDesc('deleted_at')
            ->paginate($filters['per_page'] ?? 15)
            ->withQueryString();

        return UserResource::collection($users);
    }

    public function restore(
        RestoreUserRequest $request,
        int $user
    ): UserResource {
        $trashedUser = User::onlyTrashed()
            ->findOrFail($user);

        $restoredUser = $this->userService->restore(
            actor: $request->user('api'),
            user: $trashedUser,
            reason: $request->validated('reason'),
        );

        return new UserResource($restoredUser);
    }
}
