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
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
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
        // CORS handled by Laravel's built-in HandleCors via config/cors.php (M-08)
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

        // Exclude the custom httpOnly auth token cookie from Laravel's EncryptCookies middleware.
        //
        // WHY: The login-httponly route uses ['web'] middleware, which includes EncryptCookies.
        // Laravel therefore encrypts the soleil_token cookie value before sending it to the
        // browser. All subsequent protected routes (refresh, me, bookings) run under the 'api'
        // middleware group only — EncryptCookies is absent — so $request->cookie('soleil_token')
        // returns the raw encrypted string. hash('sha256', encryptedString) never matches the
        // stored hash('sha256', plainUUID), causing token lookup to fail → 401 on every call.
        //
        // SECURITY: No regression. The cookie is already protected by:
        //   - httpOnly=true  (JavaScript cannot read it)
        //   - SameSite=Strict (no cross-site sending)
        //   - Cryptographically random UUID value (not guessable)
        $middleware->encryptCookies(except: [
            'soleil_token',
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
        $exceptions->render(function (OptimisticLockException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return ApiResponse::conflict($e->getMessage());
            }
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

        // ========== Generic HTTP exceptions (405, 429, etc.) ==========
        $exceptions->render(function (HttpException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return ApiResponse::error(
                    $e->getMessage() ?: 'HTTP error.',
                    null,
                    $e->getStatusCode()
                );
            }
        });

        // ========== Catch-all for unhandled exceptions ==========
        // Prevents stack trace leaks in production API responses.
        // Excludes HttpResponseException — Laravel uses it internally for
        // rate limiting (429), redirects, and other framework-level responses.
        $exceptions->render(function (\Throwable $e, Request $request) {
            if ($e instanceof \Illuminate\Http\Exceptions\HttpResponseException) {
                return null; // Let Laravel handle it natively
            }

            if ($request->expectsJson() || $request->is('api/*')) {
                Log::error('Unhandled API exception', [
                    'exception' => get_class($e),
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'correlation_id' => $request->attributes->get('correlation_id'),
                ]);

                $message = config('app.debug')
                    ? $e->getMessage()
                    : 'Internal server error.';

                return ApiResponse::serverError($message);
            }
        });
    })->create();
