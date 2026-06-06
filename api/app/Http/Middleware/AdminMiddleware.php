<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->hasAnyRole(['super-admin', 'admin'])) {
            abort(403, 'Táto časť je dostupná iba pre administrátora.');
        }

        return $next($request);
    }
}
