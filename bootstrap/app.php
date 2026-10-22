<?php

use App\Http\Middleware\EnsureOperationalAccountActive;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->validateCsrfTokens(except: ['stripe/webhook']);

        $middleware->appendToGroup('web', [
            SetLocale::class,
        ]);

        $middleware->alias([
            'account.active' => EnsureOperationalAccountActive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['feed_token', 'api_key', 'webhook_secret', 'delivery_api_key']);
    })->create();
