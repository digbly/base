<?php

namespace Modules\Network\Traits;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Network\Models\Website;
use Modules\Network\Observers\NetworkWebsiteObserver;

trait HasNetworkWebsite
{
    public static function bootHasNetworkWebsite(): void
    {
        static::observe(NetworkWebsiteObserver::class);
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class, 'website_id');
    }
}
