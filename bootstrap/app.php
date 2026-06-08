<?php

use Illuminate\Support\Facades\Route;
use App\Http\Middleware\CheckScreenLock;
use App\Http\Middleware\JWTMiddleware;
use Illuminate\Foundation\Application;
use App\Http\Middleware\AdminMiddleware;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        channels: __DIR__ . '/../routes/channels.php',
        health: '/up',
        then: function () {
            Route::middleware(['web', 'auth', 'lock.screen'])
                ->group(base_path('routes/backend.php'));

        }
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'jwt.verify' => JWTMiddleware::class,
            'lock.screen' => CheckScreenLock::class,
        ]);
    })
    ->withBroadcasting(
        __DIR__ . '/../routes/channels.php',
        ['prefix' => 'api', 'middleware' => ['jwt.verify']],
    )
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
