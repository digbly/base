<?php

namespace Modules\Network\Observers;

use Illuminate\Database\Eloquent\Model;

class NetworkWebsiteObserver
{
    /**
     * Handle the model "creating" event.
     *
     * Automatically assigns the current website id for models that use the
     * Networkable or HasNetworkWebsite trait.
     */
    public function creating(Model $model): void
    {
        if ($model->website_id !== null) {
            return;
        }

        if (($websiteId = website_id()) !== null) {
            $model->website_id = $websiteId;
        }
    }
}
