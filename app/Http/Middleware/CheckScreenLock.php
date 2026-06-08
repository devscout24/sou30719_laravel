<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckScreenLock
{
    public function handle(Request $request, Closure $next): Response
    {
        if (session('screen_locked')) {
            return redirect()->route('lock.screen');
        }

        return $next($request);
    }
}
