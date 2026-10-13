<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use Closure;
use Illuminate\Http\Request;

// spec.md AC-01.3: insufficient role -> 403, without revealing whether the resource exists.
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles)
    {
        if (! in_array($request->attributes->get('auth_role'), $roles, true)) {
            throw new ApiException('FORBIDDEN_ROLE', 'No tiene permiso para esta operación.', 403);
        }

        return $next($request);
    }
}
