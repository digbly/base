<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ThemeSsr
{
    /**
     * Point Inertia's SSR gateway at the active theme's bundle and server for
     * theme routes, leaving the application's own SSR configuration untouched.
     */
    public function handle(Request $request, Closure $next): Response
    {
        theme()?->registerSsr();

        return $next($request);
    }
}
