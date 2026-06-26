<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next)
    {
        if (! auth()->user()->hasRole('Super Admin')) {

            abort(403);

        }

        return $next($request);
    }
}
