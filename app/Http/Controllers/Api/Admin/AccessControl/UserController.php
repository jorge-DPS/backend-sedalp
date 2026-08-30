<?php

namespace App\Http\Controllers\Api\Admin\AccessControl;

use App\Http\Controllers\Controller;
use App\Http\Requests\AccessControl\StoreUserRequest;
use App\Http\Requests\AccessControl\UpdateUserRequest;
use App\Http\Requests\AccessControl\UpdateUserRoleRequest;
use App\Http\Resources\AccessControl\UserResource;
use App\Models\User;
use App\Services\AccessControl\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class UserController extends Controller
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
    Request $request
  ): AnonymousResourceCollection {
    $perPage = min(
      max(
        $request->integer('per_page', 15),
        1
      ),
      100
    );

    $search = trim(
      (string) $request->query(
        'search',
        ''
      )
    );

    $users = User::query()
      ->with(self::RELATIONS)
      ->when(
        $search !== '',
        fn($query) => $query->where(
          'email',
          'ILIKE',
          "%{$search}%"
        )
      )
      ->latest('id')
      ->paginate($perPage)
      ->withQueryString();

    return UserResource::collection(
      $users
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
    Request $request,
    User $user
  ): Response|JsonResponse {
    $authenticatedUser = $request->user(
      'api'
    );

    /*
         * Ningún usuario puede eliminar
         * su propia cuenta.
         */
    if ($authenticatedUser->is($user)) {
      return response()->json([
        'message' => 'No puede eliminar su propia cuenta.',
      ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /*
         * Un usuario normal no puede eliminar
         * una cuenta super_admin.
         *
         * Un super_admin sí puede eliminar
         * otro super_admin.
         */
    if (
      $user->hasRole('super_admin')
      && ! $authenticatedUser->hasRole('super_admin')
    ) {
      return response()->json([
        'message' => 'No está autorizado para eliminar un superadministrador.',
      ], Response::HTTP_FORBIDDEN);
    }

    $user->delete();

    return response()->noContent();
  }
}
