<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Illuminate\Auth\AuthenticationException;

class JwtAuthenticate
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        try {
            // Attempt to authenticate using JWT
            $user = JWTAuth::parseToken()->authenticate();
        } catch (JWTException $e) {
            // If the token is invalid or expired
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Attach the authenticated user to the request object
        $request->attributes->add(['user' => $user]);

        return $next($request);
    }
}
