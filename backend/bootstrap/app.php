<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Authorization\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use App\Exceptions\OptimisticLockException;
use App\Http\Responses\ApiResponse;

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
            'correlation_id' => \App\Http\Middleware\AddCorrelationId::class,
            'log_performance' => \App\Http\Middleware\LogPerformance::class,
        ]);

        // ========== Register global middleware ==========
        // Correlation ID should run first for request tracing
        $middleware->prepend(\App\Http\Middleware\AddCorrelationId::class);
        // CORS must run early to handle preflight requests
        $middleware->prepend(\App\Http\Middleware\Cors::class);
        // SecurityHeaders must run early in the pipeline (before response is finalized)
        $middleware->prepend(\App\Http\Middleware\SecurityHeaders::class);
        // Performance logging runs at the end to capture full request duration
        $middleware->append(\App\Http\Middleware\LogPerformance::class);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // ========== API Response Wrapper ==========
        // Only apply standardized responses for API requests
        // Checks: Accept header OR /api prefix

        // Handle validation exceptions (422)
        $exceptions->render(function (ValidationException $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return ApiResponse::validationErrors($e->errors());
            }
        });

        // Handle model not found (404)
        $exceptions->render(function (ModelNotFoundException $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                $model = class_basename($e->getModel());
                return ApiResponse::notFound("{$model} not found.");
            }
        });

        // Handle route not found (404)
        $exceptions->render(function (NotFoundHttpException $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return ApiResponse::notFound('Endpoint not found.');
            }
        });

        // ========== Optimistic Lock Conflict ==========
        // Return 409 Conflict when concurrent modification is detected
        // Client should refresh data and retry the operation
        // NOTE: Maintains legacy format for backward compatibility
        $exceptions->render(function (OptimisticLockException $e, $request) {
            return response()->json([
                'error' => 'resource_out_of_date',
                'message' => 'The room has been modified by another user. Please refresh and try again.',
            ], 409);
        });

        // Handle authorization exceptions (403)
        $exceptions->render(function (AuthorizationException $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return ApiResponse::forbidden('This action is unauthorized.');
            }
        });

        // Handle authentication exceptions (401)
        $exceptions->render(function (AuthenticationException $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return ApiResponse::unauthorized('Unauthenticated. Please log in.');
            }
        });
    })->create();
