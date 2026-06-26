<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;

class EnsureHeadOrgAdmin
{
    /**
     * Allow users with sub-organization powers through: a Super Admin, or any
     * admin (Organization Admin / Head Organization Admin) whose organization is
     * a head organization. See {@see User::canManageSubOrganizations()}.
     */
    public function handle(Request $request, Closure $next)
    {
        abort_unless(auth()->user()->canManageSubOrganizations(), 403);

        return $next($request);
    }
}
