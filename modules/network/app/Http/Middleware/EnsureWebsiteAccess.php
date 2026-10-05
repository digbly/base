<?php

namespace Modules\Network\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensure the authenticated user may administer the current website.
 *
 * Super admins may access any website; every other user must be attached to
 * the website through the `website_user` pivot. Runs after {@see \Modules\Network\Http\Middleware\InitWebsite}
 * has resolved the website from the route.
 */
class EnsureWebsiteAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $website = website();

        abort_if($website === null, 404);

        $isMember = $user->websites()
            ->whereKey($website->getKey())
            ->exists();

        abort_unless($user->isSuperAdmin() || $isMember, 403);

        return $next($request);
    }
}
