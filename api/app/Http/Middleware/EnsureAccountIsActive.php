<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user('sanctum');
        abort_if($user && ! $user->isActive(), 403, 'Váš účet nie je aktívny.');

        return $next($request);
    }
}
