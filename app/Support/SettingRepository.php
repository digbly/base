<?php

namespace App\Support;

use App\Contracts\Setting as SettingContract;
use App\Models\Setting as SettingModel;
use App\Support\Entities\Setting;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Collection;

class SettingRepository implements SettingContract
{
    protected ?string $locale = null;

    public function __construct(
        protected CacheRepository $cache
    ) {
        //
    }

    public function locale(string $locale): static
    {
        $this->locale = $locale;

        return $this;
    }

    public function make(string $key): Setting
    {
        return app(Setting::class, ['key' => $key]);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->configs()->get($key) ?? $this->settings()->get($key)['default'] ?? $default;
    }

    public function boolean(string $key, mixed $default = null): ?bool
    {
        $value = $this->get($key, $default);

        if ($value === null) {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public function integer(string $key, mixed $default = null): ?int
    {
        $value = $this->get($key, $default);

        if ($value === null) {
            return null;
        }

        return (int) $value;
    }

    public function float(string $key, mixed $default = null): ?float
    {
        $value = $this->get($key, $default);

        if ($value === null) {
            return null;
        }

        return (float) $value;
    }

    public function set(string $key, mixed $value = null): SettingModel
    {
        $definition = $this->settings()->get($key);
        $translatable = (bool) ($definition['translatable'] ?? false);

        $model = SettingModel::updateOrCreate(
            [
                'code' => $key,
            ],
            [
                'value' => $value,
                'translatable' => $translatable,
            ]
        );

        if ($translatable) {
            $translation = $model->translateOrNew($this->locale ?? app()->getLocale());
            $translation->lang_value = is_array($value) ? json_encode($value) : $value;
            $model->save();
        }

        $this->flushCache();

        return $model;
    }

    public function sets(array $keys): Collection
    {
        foreach ($keys as $key => $value) {
            $this->set($key, $value);
        }

        return $this->configs()->only(array_keys($keys));
    }

    public function gets(array $keys, mixed $default = null): array
    {
        $data = [];

        foreach ($keys as $key) {
            $data[$key] = $this->get($key, $default);
        }

        return $data;
    }

    public function all(): Collection
    {
        $configs = $this->configs();

        return $this->settings()->map(
            fn ($setting) => $configs[$setting['key']] ?? $setting['default'] ?? null
        );
    }

    public function keys(?array $keys = null): Collection
    {
        if (is_null($keys)) {
            return $this->settings()->keys();
        }

        return $this->settings()->only($keys)->keys();
    }

    public function settings(): Collection
    {
        return new Collection(SettingsRegistry::all());
    }

    public function localized(): Collection
    {
        return SettingModel::query()
            ->with('translations')
            ->where('translatable', true)
            ->get()
            ->mapWithKeys(fn (SettingModel $item) => [
                $item->code => $item->translations->pluck('lang_value', 'locale'),
            ]);
    }

    public function configs(): Collection
    {
        $settings = $this->cache->remember($this->cacheKey(), 3600, function () {
            return SettingModel::query()
                ->with('translations')
                ->get()
                ->all();
        });

        $locale = $this->locale ?? app()->getLocale();

        return (new Collection($settings))->mapWithKeys(function (SettingModel $item) use ($locale) {
            $value = $item->translatable
                ? $item->translate($locale)?->lang_value
                : $item->value;

            return [$item->code => $value];
        });
    }

    protected function cacheKey(): string
    {
        return 'settings.configs';
    }

    protected function flushCache(): void
    {
        $this->cache->forget($this->cacheKey());
    }
}
