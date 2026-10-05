<?php

namespace App\Models;

use Astrotomic\Translatable\Contracts\Translatable as TranslatableContract;
use Astrotomic\Translatable\Translatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Modules\Network\Traits\Networkable;

class ThemeSidebar extends Model implements TranslatableContract
{
    use HasUuids, Networkable, Translatable;

    protected $table = 'theme_sidebars';

    protected $fillable = [
        'sidebar',
        'widget',
        'data',
        'theme',
        'display_order',
        'website_id',
    ];

    protected $casts = [
        'data' => 'array',
    ];

    public array $translatedAttributes = [
        'label',
        'fields',
        'locale',
    ];

    public function scopeWhereSidebar(Builder $builder, string $sidebar): Builder
    {
        return $builder->where('sidebar', $sidebar);
    }

    public function scopeOrdered(Builder $builder): Builder
    {
        return $builder->orderBy('display_order');
    }
}
