<?php

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
        // The control panel is reachable only through Traefik. Trust the immediate
        // proxy so Laravel honors its forwarded host, port, and HTTPS scheme.
        $middleware->trustProxies(at: '*');

        $middleware->validateCsrfTokens(except: ['stripe/webhook']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
