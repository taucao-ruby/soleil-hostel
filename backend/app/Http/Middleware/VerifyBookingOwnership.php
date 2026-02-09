<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyBookingOwnership
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $booking = $request->route('booking');
        
        if ($booking && $booking->user_id !== auth()->id() && !auth()->user()?->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to access this booking.',
            ], 403);
        }

        return $next($request);
    }
}
