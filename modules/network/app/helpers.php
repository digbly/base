<?php

use Modules\Network\Contracts\Network as NetworkContract;
use Modules\Network\Models\Website;

if (! function_exists('website')) {
    function website(): ?Website
    {
        if (! app()->bound(NetworkContract::class)) {
            return null;
        }

        return app(NetworkContract::class)->website();
    }
}

if (! function_exists('website_id')) {
    function website_id(): int|string|null
    {
        return website()?->id ?? config('app.website_id');
    }
}

if (! function_exists('admin_url')) {
    function admin_url(?string $uri = null, int|string|null $websiteId = null): string
    {
        $websiteId ??= website_id();

        $segments = array_filter(
            [config('app.admin_prefix', 'admin'), $websiteId, ltrim((string) $uri, '/')],
            fn ($segment) => $segment !== null && $segment !== ''
        );

        return url(implode('/', $segments));
    }
}
