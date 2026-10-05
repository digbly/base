<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Modules\Network\Traits\Networkable;

class Language extends Model
{
    use Networkable;

    protected $table = 'languages';

    protected $fillable = [
        'code',
        'name',
        'website_id',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    /**
     * All languages of the current website keyed by their code.
     */
    public static function languages(): Collection
    {
        return static::query()->get()->keyBy('code');
    }

    /**
     * @return list<string>
     */
    public static function codes(): array
    {
        return array_values(static::languages()->keys()->all());
    }

    /**
     * @return list<string>
     */
    public static function codesWithoutFallback(): array
    {
        return array_values(
            static::languages()
                ->keys()
                ->reject(fn (string $code) => $code === config('translatable.fallback_locale'))
                ->all()
        );
    }

    /**
     * The default language code for the current website.
     */
    public static function default(): string
    {
        $default = static::query()->where('is_default', true)->value('code');

        return $default ?? config('translatable.fallback_locale');
    }

    public static function existsCode(string $code): bool
    {
        return static::query()->where('code', $code)->exists();
    }
}
