<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountIsActive
{
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        /** @var User|null $user */
        $user = $request->user('api');

        /*
         * auth:api debe ejecutarse antes.
         * Esta comprobación es defensiva.
         */
        if ($user === null) {
            return response()->json([
                'message' => 'No autenticado.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        if ($request->bearerToken() !== null) {
            try {
                $tokenVersion = (int) auth('api')
                    ->payload()
                    ->get('ver');
            } catch (JWTException) {
                return response()->json([
                    'message' => 'No autenticado.',
                ], Response::HTTP_UNAUTHORIZED);
            }

            if ($tokenVersion !== $user->token_version) {
                return response()->json([
                    'message' => 'La sesión fue revocada.',
                ], Response::HTTP_UNAUTHORIZED);
            }
        }

        if (! $user->canAccessApi()) {
            return response()->json([
                'message' => 'Cuenta inhabilitada.',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
