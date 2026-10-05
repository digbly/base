<?php

namespace Modules\Network\Enums;

enum WebsitePermission: string
{
    case View = 'websites.view';
    case Create = 'websites.create';
    case Update = 'websites.update';
    case Delete = 'websites.delete';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case) => $case->value, self::cases());
    }
}
