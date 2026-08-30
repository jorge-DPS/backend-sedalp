<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => [
                'required',
                'email',
            ],
            'password' => [
                'required',
                'string',
            ],
        ]);

        $guard = auth('api');

        if (! $token = $guard->attempt($credentials)) {
            return response()->json([
                'message' => 'Credenciales incorrectas.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        /** @var User $user */
        $user = $guard->user();

        /*
         * Las cuentas técnicas pueden existir sin
         * StaffMember asociado.
         *
         * Si existe vínculo con personal, dicho personal
         * debe seguir existiendo, no estar eliminado
         * lógicamente y encontrarse activo.
         */
        if (! $user->canAccessApi()) {
            /*
             * attempt() ya generó un JWT.
             *
             * Como no permitiremos iniciar sesión,
             * invalidamos inmediatamente ese token.
             */
            $guard->logout();

            return response()->json([
                'message' => 'Cuenta inhabilitada.',
            ], Response::HTTP_FORBIDDEN);
        }

        return response()->json([
            'message' => 'Inicio de sesión exitoso.',
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => $guard
                ->factory()
                ->getTTL() * 60,
        ]);
    }

    public function me(): JsonResponse
    {
        /** @var User $user */
        $user = auth('api')->user();

        $roles = $user
            ->getRoleNames()
            ->values()
            ->all();

        $permissions = $user
            ->getAllPermissions()
            ->pluck('name')
            ->values()
            ->all();

        $user->makeHidden([
            'roles',
            'permissions',
        ]);

        return response()->json([
            'user' => $user,
            'authorization' => [
                'roles' => $roles,
                'permissions' => $permissions,
            ],
        ]);
    }

    public function logout(): JsonResponse
    {
        auth('api')->logout();

        return response()->json([
            'message' => 'Sesión cerrada correctamente.',
        ]);
    }

    public function refresh(): JsonResponse
    {
        $guard = auth('api');

        try {
            /*
             * Puede renovar un token cuyo access TTL ya expiró,
             * siempre que siga dentro de refresh_ttl.
             */
            $token = $guard->refresh();

            /*
             * Obtenemos el usuario al que pertenece
             * el nuevo token.
             */
            $userId = $guard
                ->setToken($token)
                ->payload()
                ->get('sub');

            $user = User::find($userId);

            /*
             * El usuario pudo haber sido eliminado
             * después de emitir el token original.
             */
            if ($user === null) {
                $guard
                    ->setToken($token)
                    ->invalidate();

                return response()->json([
                    'message' => 'La cuenta asociada al token ya no está disponible.',
                ], Response::HTTP_UNAUTHORIZED);
            }

            /*
             * Si la cuenta está vinculada a personal,
             * ese personal debe continuar activo.
             */
            if (! $user->canAccessApi()) {
                $guard
                    ->setToken($token)
                    ->invalidate();

                return response()->json([
                    'message' => 'Cuenta inhabilitada.',
                ], Response::HTTP_FORBIDDEN);
            }

            return response()->json([
                'access_token' => $token,
                'token_type' => 'bearer',
                'expires_in' => $guard->factory()->getTTL() * 60,
            ]);

        } catch (JWTException) {
            return response()->json([
                'message' => 'El token no puede ser renovado.',
            ], Response::HTTP_UNAUTHORIZED);
        }
    }
}
