<?php

namespace Modules\Network\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Network\Models\Website;
use Modules\Network\Observers\NetworkWebsiteObserver;

trait Networkable
{
    public static function bootNetworkable(): void
    {
        static::observe(NetworkWebsiteObserver::class);

        static::addGlobalScope('website_id', function (Builder $builder) {
            $websiteId = website_id();

            if ($websiteId !== null) {
                $builder->where(
                    $builder->getModel()->getTable().'.website_id',
                    $websiteId
                );
            }
        });
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class, 'website_id');
    }
}
