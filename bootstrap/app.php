<?php

use App\Http\Middleware\EnforceSessionRules;
use App\Http\Middleware\EnsureMfaVerified;
use App\Http\Middleware\EnsureRecentMfa;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Security headers on every response, including errors and the /up health check.
        $middleware->append(SecurityHeaders::class);

        // Sign-in rules on every web request (12-hour limit, inactive accounts).
        $middleware->appendToGroup('web', EnforceSessionRules::class);

        $middleware->alias([
            'mfa' => EnsureMfaVerified::class,
            'mfa.fresh' => EnsureRecentMfa::class,
        ]);

        // Signed-out visitors are sent to the sign-in page.
        $middleware->redirectGuestsTo(fn () => route('login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
