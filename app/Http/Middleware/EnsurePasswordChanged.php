<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Holds an authenticated user on a temporary password at the first-sign-in
 * password screen until they replace it. Appended to the `web` group, so it
 * guards every page — not just the one immediately after login. Guests and
 * users without `must_change_password` pass straight through, and the
 * password-change + logout routes are exempt to avoid a redirect loop.
 */
class EnsurePasswordChanged
{
    /**
     * Routes a user on a temporary password is still allowed to reach.
     */
    protected array $allowed = [
        'password.first-change',
        'password.first-change.update',
        'logout',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (
            $user
            && $user->must_change_password
            && ! $request->routeIs(...$this->allowed)
        ) {
            return redirect()->route('password.first-change');
        }

        return $next($request);
    }
}
