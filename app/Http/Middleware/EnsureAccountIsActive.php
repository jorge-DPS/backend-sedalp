<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
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

        if (! $user->canAccessApi()) {
            return response()->json([
                'message' => 'Cuenta inhabilitada.',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
