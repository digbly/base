<?php

namespace Modules\Network\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Network\Facades\Network;
use Symfony\Component\HttpFoundation\Response;

class InitWebsite
{
    /**
     * Initialize the network website context from the route parameter.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $website = $request->route('website') ?? $request->route('websiteId');

        if ($website !== null) {
            Network::init($website);
        }

        return $next($request);
    }
}
