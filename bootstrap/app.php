<?php

use App\Http\Middleware\EnsureHeadOrgAdmin;
use App\Http\Middleware\EnsureOrgAdmin;
use App\Http\Middleware\EnsurePasswordChanged;
use App\Http\Middleware\EnsureSubscribed;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\Firewall;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {

        $middleware->alias([
            'superadmin' => EnsureSuperAdmin::class,
            'headorg.admin' => EnsureHeadOrgAdmin::class,
            'org.admin' => EnsureOrgAdmin::class,
        ]);

        // Behind OpenShift's edge-terminating router the pod receives plain HTTP,
        // so without trusting the router Laravel thinks the request is http and
        // emits http:// form actions and asset URLs that browsers block as mixed
        // content on the https page (unstyled page + failing login). Trust the
        // router's X-Forwarded-Proto (correct https scheme) and X-Forwarded-For
        // (real client IP for the firewall + audit logs). Host is read from the
        // preserved Host header, not X-Forwarded-Host, to avoid host spoofing.
        $middleware->trustProxies(at: '*', headers: Request::HEADER_X_FORWARDED_FOR
            | Request::HEADER_X_FORWARDED_PORT
            | Request::HEADER_X_FORWARDED_PROTO);

        // Application firewall runs FIRST — inspect/block attacks and harden every
        // response before anything else in the web stack sees the request.
        $middleware->web(prepend: [
            Firewall::class,
        ]);

        // Force a first-sign-in password reset for admin-created accounts, then
        // hold unpaid organizations at the billing page until they subscribe.
        $middleware->web(append: [
            EnsurePasswordChanged::class,
            EnsureSubscribed::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
