<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasPermissionGroup
{
    public function handle(Request $request, Closure $next, string $permission_group): Response
    {
        if (! $request->user()) {
            return redirect()->route('login');
        }

        if (! $request->user()->hasPermissionGroup($permission_group)) {
            abort(403, "Groupe de permission requis : {$permission_group}");
        }

        return $next($request);
    }
}
