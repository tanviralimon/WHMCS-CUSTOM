<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware(['web', 'auth'])
                ->prefix('client')
                ->name('client.')
                ->group(base_path('routes/client.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'whmcs.own' => \App\Http\Middleware\EnsureWhmcsOwnership::class,
            'feature'   => \App\Http\Middleware\EnsureFeatureEnabled::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->renderable(function (\App\Exceptions\WhmcsApiException $e, $request) {
            if ($request->expectsJson() || $request->header('X-Inertia')) {
                return back()->withErrors(['whmcs' => $e->getMessage()]);
            }
            abort(500, $e->getMessage());
        });
    })->create();
