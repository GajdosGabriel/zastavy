<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DashboardMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->hasAnyRole(['super-admin', 'admin', 'manager', 'sales', 'warehouse'])) {
            abort(403, 'Nemáte oprávnenie na túto akciu.');
        }

        return $next($request);
    }
}
