<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SuperAdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->hasRole('super-admin')) {
            abort(403, 'Táto časť je dostupná iba pre super administrátora.');
        }

        return $next($request);
    }
}
