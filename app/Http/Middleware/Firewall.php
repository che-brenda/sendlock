<?php

namespace App\Http\Middleware;

use App\Models\SecurityEvent;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Application firewall (WAF). Prepended to the `web` group so it runs FIRST:
 *
 *   1. Inspects the request line (path + query) and client for attack signatures
 *      — path traversal, XSS, SQLi, LFI/RFI, code-exec, scanner user-agents,
 *      oversized URIs — and blocks (403) + records the attempt. It never scans
 *      the request BODY, because this app legitimately analyses malicious-looking
 *      email content submitted in the body.
 *   2. Hardens every response with security headers (CSP, X-Frame-Options,
 *      nosniff, Referrer-Policy, Permissions-Policy, and HSTS over HTTPS).
 */
class Firewall
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('firewall.enabled', true)) {
            $rule = $this->inspect($request);

            if ($rule !== null) {
                $this->record($request, $rule);

                abort(403, 'This request was blocked by the SendLock firewall.');
            }
        }

        $response = $next($request);

        $this->harden($request, $response);

        return $response;
    }

    /** Return the name of the tripped rule, or null if the request is clean. */
    protected function inspect(Request $request): ?string
    {
        $uri = $request->getRequestUri();

        if (strlen($uri) > (int) config('firewall.max_uri_length', 2048)) {
            return 'oversized_uri';
        }

        // Decode so encoded payloads (%2e%2e, %3Cscript) are caught too.
        $line = rawurldecode($uri);

        foreach ((array) config('firewall.malicious_patterns', []) as $name => $pattern) {
            if (@preg_match($pattern, $line) === 1) {
                return $name;
            }
        }

        $agent = strtolower((string) $request->userAgent());

        foreach ((array) config('firewall.blocked_agents', []) as $bad) {
            if ($bad !== '' && str_contains($agent, strtolower($bad))) {
                return 'blocked_agent';
            }
        }

        return null;
    }

    protected function record(Request $request, string $rule): void
    {
        // The firewall runs before session start, so resolve the user defensively.
        $user = null;
        try {
            $user = $request->user();
        } catch (\Throwable $e) {
            // no authenticated user context
        }

        try {
            SecurityEvent::create([
                'rule' => $rule,
                'ip_address' => $request->ip(),
                'method' => $request->method(),
                'path' => Str::limit($request->getRequestUri(), 2000, ''),
                'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
                'user_id' => $user?->id,
                'organization_id' => $user?->organization_id,
            ]);
        } catch (\Throwable $e) {
            // Never let a logging failure interfere with blocking the request.
        }
    }

    protected function harden(Request $request, Response $response): void
    {
        foreach ((array) config('firewall.headers', []) as $key => $value) {
            if (! $response->headers->has($key)) {
                $response->headers->set($key, $value);
            }
        }

        if ($request->isSecure() && ($hsts = config('firewall.hsts'))) {
            $response->headers->set('Strict-Transport-Security', $hsts);
        }
    }
}
