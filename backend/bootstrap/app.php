<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Authorization\AuthorizationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // ========== Register custom middleware aliases ==========
        $middleware->alias([
            'check_token_valid' => \App\Http\Middleware\CheckTokenNotRevokedAndNotExpired::class,
            'check_httponly_token' => \App\Http\Middleware\CheckHttpOnlyTokenValid::class,
            'role' => \App\Http\Middleware\EnsureUserHasRole::class,
        ]);

        // ========== Register global middleware ==========
        // CORS must run first to handle preflight requests
        $middleware->prepend(\App\Http\Middleware\Cors::class);
        // SecurityHeaders must run early in the pipeline (before response is finalized)
        $middleware->prepend(\App\Http\Middleware\SecurityHeaders::class);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Handle authorization exceptions
        $exceptions->render(function (AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'This action is unauthorized.',
            ], 403);
        });

        // Handle authentication exceptions
        $exceptions->render(function (AuthenticationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated. Please log in.',
            ], 401);
        });
    })->create();
