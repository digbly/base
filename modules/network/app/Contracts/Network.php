<?php

namespace Modules\Network\Contracts;

use Modules\Network\Models\Website;
use Modules\Network\Support\NetworkRepository;

/**
 * @see NetworkRepository
 */
interface Network
{
    public function init(null|string|Website $website = null): void;

    public function website(): ?Website;

    public function currentIsMainSite(): bool;
}
