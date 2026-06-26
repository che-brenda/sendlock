<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureOrgAdmin
{
    /**
     * Allow organization-administration roles through (org admin and above).
     * Gates user/department/policy management within a tenant.
     */
    public function handle(Request $request, Closure $next)
    {
        $allowed = [
            'Super Admin',
            'Head Organization Admin',
            'Organization Admin',
        ];

        if (! auth()->user()->hasAnyRole($allowed)) {

            abort(403);

        }

        return $next($request);
    }
}
