<?php

namespace App\Http\Controllers\Api\Admin\AccessControl;

use App\Http\Controllers\Controller;
use App\Http\Requests\AccessControl\DeleteUserRequest;
use App\Http\Requests\AccessControl\IndexUserRequest;
use App\Http\Requests\AccessControl\StoreUserRequest;
use App\Http\Requests\AccessControl\UpdateUserRequest;
use App\Http\Requests\AccessControl\UpdateUserRoleRequest;
use App\Http\Resources\AccessControl\UserResource;
use App\Models\User;
use App\Services\AccessControl\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class UserController extends Controller
{
    public function __construct(
        private readonly UserService $userService
    ) {}

    public function index(
        IndexUserRequest $request
    ): AnonymousResourceCollection {
        return UserResource::collection(
            $this->userService->paginate(
                $request->validated()
            )
        );
    }

    public function store(
        StoreUserRequest $request
    ): JsonResponse {
        $user = $this->userService->create(
            $request->validated()
        );

        return (new UserResource($user))
            ->response()
            ->setStatusCode(
                Response::HTTP_CREATED
            );
    }

    public function show(
        User $user
    ): UserResource {
        return new UserResource(
            $this->userService->load(
                $user
            )
        );
    }

    public function update(
        UpdateUserRequest $request,
        User $user
    ): UserResource {
        $updatedUser = $this->userService->update(
            actor: $request->user('api'),
            user: $user,
            data: $request->validated(),
        );

        return new UserResource(
            $updatedUser
        );
    }

    public function updateRole(
        UpdateUserRoleRequest $request,
        User $user
    ): UserResource {
        $updatedUser = $this->userService->updateRole(
            actor: $request->user('api'),
            user: $user,
            role: $request->validated('role'),
        );

        return new UserResource(
            $updatedUser
        );
    }

    public function destroy(
        DeleteUserRequest $request,
        User $user
    ): Response {
        $this->userService->delete(
            actor: $request->user('api'),
            user: $user,
            reason: $request->validated('reason'),
        );

        return response()->noContent();
    }
}
