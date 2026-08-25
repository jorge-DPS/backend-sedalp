<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

use App\Enums\Auth\RoleName;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('login', function (Request $request) {
            $email = Str::lower((string) $request->input('email'));

            return Limit::perMinute(5)
                ->by($email . '|' . $request->ip())
                ->response(function (
                    Request $request,
                    array $headers
                ) {
                    return response()->json([
                        'message' => 'Demasiados intentos de inicio de sesión. Intente nuevamente en un momento.',
                    ], 429, $headers);
                });
        });

        Gate::before(function (User $user, string $ability) {
            return $user->hasRole(RoleName::SUPER_ADMIN->value)
                ? true
                : null;
        });
    }
}
