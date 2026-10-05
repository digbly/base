<?php

namespace App\Models;

use Modules\Network\Traits\Networkable;
use Spatie\TranslationLoader\LanguageLine as BaseLanguageLine;

class LanguageLine extends BaseLanguageLine
{
    use Networkable;
}
