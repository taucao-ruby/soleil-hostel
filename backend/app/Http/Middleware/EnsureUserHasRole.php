<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Http\Responses\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to enforce minimum role level for route access.
 *
 * Uses the role hierarchy: USER < MODERATOR < ADMIN
 * A user with a higher role can access routes requiring lower roles.
 *
 * Usage in routes:
 *   Route::middleware('role:admin')->group(...);
 *   Route::middleware('role:moderator')->group(...);
 *
 * @example Route::get('/admin/users', [UserController::class, 'index'])->middleware('role:admin');
 */
class EnsureUserHasRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  $role  The minimum role required (user, moderator, admin)
     */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $user = $request->user();

        if (! $user) {
            return ApiResponse::unauthorized('Unauthenticated.');
        }

        $requiredRole = UserRole::tryFrom($role);

        if ($requiredRole === null) {
            // Invalid role parameter - log and deny
            report(new \InvalidArgumentException("Invalid role parameter in middleware: {$role}"));

            return ApiResponse::serverError('Server configuration error.');
        }

        if (! $user->isAtLeast($requiredRole)) {
            return ApiResponse::forbidden('Forbidden. Insufficient permissions.');
        }

        return $next($request);
    }
}
