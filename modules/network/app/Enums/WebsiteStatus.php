<?php

namespace Modules\Network\Enums;

use Illuminate\Support\Str;

enum WebsiteStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case SUSPENDED = 'suspended';

    public static function all(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn ($case) => [$case->value => $case->label()])
            ->toArray();
    }

    public function label(): string
    {
        return Str::headline(strtolower($this->name));
    }
}
