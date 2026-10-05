<?php

namespace Modules\Network\Models;

use Illuminate\Database\Eloquent\Model;

class Database extends Model
{
    protected $table = 'databases';

    protected $fillable = [
        'name',
        'total_websites',
    ];
}
