<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    /**
     * Handle an incoming request.
     *
     * Checks the role pivot directly instead of Spatie's guard-aware
     * hasRole(), since roles in this project are seeded against the
     * 'web' guard while API auth runs on the 'api' guard.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('api')->user();

        if (!$user || !$user->roles()->where('name', 'admin')->exists()) {
            return response()->json([
                'status' => false,
                'message' => 'Forbidden',
                'data' => [],
                'code' => 403,
            ], 403);
        }

        return $next($request);
    }
}
