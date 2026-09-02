<?php

namespace App\Http\Controllers\Api\Admin\AccessControl;

use App\Enums\Auth\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\AccessControl\UpdateUserStatusRequest;
use App\Http\Resources\AccessControl\UserResource;
use App\Models\User;
use App\Services\AccessControl\UserService;

class UserStatusController extends Controller
{
    public function __construct(
        private readonly UserService $userService
    ) {}

    public function __invoke(
        UpdateUserStatusRequest $request,
        User $user
    ): UserResource {
        $user = $this->userService->updateStatus(
            actor: $request->user('api'),
            user: $user,
            status: UserStatus::from(
                $request->validated('status')
            ),
            reason: $request->validated('reason'),
        );

        return new UserResource($user);
    }
}
