<?php

namespace Modules\Network\Facades;

use Illuminate\Support\Facades\Facade;
use Modules\Network\Contracts\Network as NetworkContract;
use Modules\Network\Support\NetworkRepository;

/**
 * @method static void init(null|string|\Modules\Network\Models\Website $website = null)
 * @method static \Modules\Network\Models\Website|null website()
 * @method static bool currentIsMainSite()
 *
 * @see NetworkRepository
 */
class Network extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return NetworkContract::class;
    }
}
