<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class DevAutoLogin
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Only in local environment with DEV_AUTO_LOGIN_USERNAME set
        if (app()->environment('local') && config('app.dev_auto_login_username')) {
            // If not already authenticated
            if (! Auth::check()) {
                $username = config('app.dev_auto_login_username');
                $user = User::where('username', $username)->first();

                if ($user) {
                    Auth::login($user);
                }
            }
        }

        return $next($request);
    }
}
