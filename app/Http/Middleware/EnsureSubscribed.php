<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Holds a freshly-registered organization at the billing page until it pays for
 * a package. Appended to the `web` group, so it guards every page. Only orgs
 * with `subscription_status = 'pending'` are gated — Super Admins (no org) and
 * pre-existing orgs (null status) pass straight through. The billing routes,
 * the first-sign-in password reset, and logout are exempt to avoid a loop.
 */
class EnsureSubscribed
{
    protected array $allowed = [
        'billing.index',
        'billing.free',
        'billing.checkout',
        'billing.process',
        'password.first-change',
        'password.first-change.update',
        'logout',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (
            $user
            && $user->organization?->subscriptionPending()
            && ! $request->routeIs(...$this->allowed)
        ) {
            return redirect()->route('billing.index');
        }

        return $next($request);
    }
}
