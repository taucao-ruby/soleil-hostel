<?php

use App\Exceptions\OptimisticLockException;
use App\Http\Responses\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Authorization\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // ========== Register custom middleware aliases ==========
        $middleware->alias([
            'check_token_valid' => \App\Http\Middleware\CheckTokenNotRevokedAndNotExpired::class,
            'check_httponly_token' => \App\Http\Middleware\CheckHttpOnlyTokenValid::class,
            'role' => \App\Http\Middleware\EnsureUserHasRole::class,
            'correlation_id' => \App\Http\Middleware\AddCorrelationId::class,
            'log_performance' => \App\Http\Middleware\LogPerformance::class,
            'verified' => \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,
            'signed' => \Illuminate\Routing\Middleware\ValidateSignature::class,
            'deprecated' => \App\Http\Middleware\DeprecatedEndpoint::class,
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

        // login-httponly uses 'web' middleware to start a session so it can return a real
        // CSRF token.  The login request itself cannot carry a CSRF token (it is the first
        // request), so exclude it from CSRF verification.
        $middleware->validateCsrfTokens(except: [
            'api/auth/login-httponly',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // ========== API Response Wrapper ==========
        // Only apply standardized responses for API requests
        // Checks: Accept header OR /api prefix

        // Handle validation exceptions (422)
        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return ApiResponse::validationErrors($e->errors());
            }
        });

        // Handle model not found (404)
        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                $model = class_basename($e->getModel());

                return ApiResponse::notFound("{$model} not found.");
            }
        });

        // Handle route not found (404)
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return ApiResponse::notFound('Endpoint not found.');
            }
        });

        // ========== Optimistic Lock Conflict ==========
        // Return 409 Conflict when concurrent modification is detected
        // Client should refresh data and retry the operation
        // NOTE: Maintains legacy format for backward compatibility
        $exceptions->render(function (OptimisticLockException $e, Request $request) {
            return response()->json([
                'error' => 'resource_out_of_date',
                'message' => 'The room has been modified by another user. Please refresh and try again.',
            ], 409);
        });

        // Handle authorization exceptions (403)
        $exceptions->render(function (AuthorizationException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return ApiResponse::forbidden('This action is unauthorized.');
            }
        });

        // Handle authentication exceptions (401)
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return ApiResponse::unauthorized('Unauthenticated. Please log in.');
            }
        });
    })->create();
