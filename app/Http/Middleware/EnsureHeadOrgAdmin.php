<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureHeadOrgAdmin
{
    /**
     * Allow only platform owners and head-organization admins through.
     * These roles can manage sub-organizations.
     */
    public function handle(Request $request, Closure $next)
    {
        if (! auth()->user()->hasAnyRole(['Super Admin', 'Head Organization Admin'])) {

            abort(403);

        }

        return $next($request);
    }
}
