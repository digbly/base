<?php

namespace App\Support;

use App\Facades\AdminTranslation;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Lang;

/**
 * Resolves where the admin SPA translation namespaces live. Namespaces are
 * registered by their owner (the application shell or a feature module) through
 * the {@see AdminTranslation} registry; this service turns them into the
 * payload the Inertia frontend and the settings screen consume.
 */
class AdminTranslations
{
    /**
     * @return array<string, array{group: string, path?: string}>
     */
    public function namespaces(): array
    {
        return AdminTranslation::all();
    }

    /**
     * Backend language group for a frontend namespace.
     */
    public function group(string $namespace): string
    {
        return $this->namespaces()[$namespace]['group'];
    }

    /**
     * Translation key (`group` or `namespace::group`) used to resolve a
     * namespace through the translator. Module namespaces carry a `path` and
     * are resolved through their own translation namespace; application
     * namespaces resolve against the global `resources/lang`.
     */
    public function translationKey(string $namespace): string
    {
        $definition = $this->namespaces()[$namespace] ?? [];

        return isset($definition['path']) ? $namespace.'::'.$definition['group'] : $definition['group'];
    }

    /**
     * Register every module-owned language directory as a translation
     * namespace. The owning provider only boots when the module is enabled, so
     * this mirrors the namespace registry itself.
     */
    public function registerNamespaces(): void
    {
        foreach ($this->namespaces() as $namespace => $definition) {
            $path = $definition['path'] ?? null;

            if ($path !== null && File::isDirectory($path)) {
                Lang::addNamespace($namespace, $path);
            }
        }
    }

    /**
     * Every locale that ships admin translations, from the application and any
     * owning module, so a locale added to either place is exposed.
     *
     * @return list<string>
     */
    public function locales(): array
    {
        $directories = collect([lang_path(), ...$this->moduleLangPaths()]);

        return $directories
            ->filter(fn (string $directory) => File::isDirectory($directory))
            ->flatMap(fn (string $directory) => File::directories($directory))
            ->map(fn (string $directory) => basename($directory))
            ->reject(fn (string $locale) => $locale === 'vendor')
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * Language directories owned by the registered module namespaces.
     *
     * @return list<string>
     */
    protected function moduleLangPaths(): array
    {
        return collect($this->namespaces())
            ->pluck('path')
            ->filter()
            ->values()
            ->all();
    }
}
