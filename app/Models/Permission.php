<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\Models\Permission as SpatiePermission;

class Permission extends SpatiePermission
{
    protected static function booted(): void
    {
        static::addGlobalScope('website_id', function (Builder $builder) {
            $websiteId = website_id();

            if ($websiteId === null) {
                return;
            }

            $table = $builder->getModel()->getTable();

            $builder->where(
                fn (Builder $query) => $query
                    ->where("{$table}.website_id", $websiteId)
                    ->orWhereNull("{$table}.website_id")
            );
        });
    }
}
