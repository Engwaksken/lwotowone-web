<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RequirePasswordChange
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->user()?->must_change_password
            && ! $request->is('change-password')
            && ! $request->is('logout')
            && ! $request->is('api/change-password')
            && ! $request->is('api/logout')) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Change your temporary password before continuing.'], 403)
                : redirect('/change-password');
        }

        return $next($request);
    }
}
