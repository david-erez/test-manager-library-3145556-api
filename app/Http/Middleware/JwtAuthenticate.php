<?php

namespace App\Http\Middleware;

use App\Support\Jwt;
use Closure;
use Firebase\JWT\ExpiredException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use UnexpectedValueException;

// spec.md AC-01.3: every endpoint except login and /health requires a token.
class JwtAuthenticate
{
    public function handle(Request $request, Closure $next)
    {
        $header = $request->header('Authorization', '');

        if (! str_starts_with($header, 'Bearer ')) {
            throw new AuthenticationException();
        }

        try {
            $claims = Jwt::decode(substr($header, 7));
        } catch (ExpiredException|UnexpectedValueException $e) {
            throw new AuthenticationException();
        }

        $request->attributes->set('auth_user_id', $claims->sub);
        $request->attributes->set('auth_role', $claims->role);

        return $next($request);
    }
}
