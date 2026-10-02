<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Prihlasovací token žije v httpOnly cookie (JavaScript ho nevidí, takže ho XSS nevykradne).
 * Sanctum však očakáva Bearer hlavičku, tak ju z cookie doplníme, ak ju klient neposlal sám.
 */
class AuthTokenFromCookie
{
    public const COOKIE = 'auth_token';

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->bearerToken() && is_string($token = $request->cookie(self::COOKIE)) && $token !== '') {
            $request->headers->set('Authorization', 'Bearer '.$token);
        }

        return $next($request);
    }
}
