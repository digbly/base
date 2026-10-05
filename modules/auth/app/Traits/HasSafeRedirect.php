<?php

namespace Modules\Auth\Traits;

trait HasSafeRedirect
{
    /**
     * Only allow internal, single-slash relative redirect targets.
     */
    protected function safeRedirect(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }

        if (! str_starts_with($url, '/') || str_starts_with($url, '//')) {
            return null;
        }

        return $url;
    }
}
