<?php

namespace App\Support;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Lang;

/**
 * Resolves where the admin SPA translation namespaces live: application
 * `resources/lang` by default, or an owning module's `lang` directory for
 * strings that belong to a feature module (e.g. the auth screens).
 */
class AdminTranslations
{
    /**
     * @return array<string, array{group: string, module?: string}>
     */
    public function namespaces(): array
    {
        return config('admin-translations.namespaces', []);
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
     * namespace through the translator.
     */
    public function translationKey(string $namespace): string
    {
        $group = $this->group($namespace);

        return isset($this->namespaces()[$namespace]['module']) ? $namespace.'::'.$group : $group;
    }

    /**
     * Register every module-owned language directory as a translation
     * namespace. Modules are not activated in every environment, so this runs
     * for the configured owners regardless of activation status.
     */
    public function registerNamespaces(): void
    {
        foreach ($this->namespaces() as $namespace => $definition) {
            $module = $definition['module'] ?? null;

            if ($module === null) {
                continue;
            }

            $path = module_path($module, 'lang');

            if (File::isDirectory($path)) {
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
     * Language directories owned by the configured modules.
     *
     * @return list<string>
     */
    protected function moduleLangPaths(): array
    {
        return collect($this->namespaces())
            ->pluck('module')
            ->filter()
            ->map(fn (string $module) => module_path($module, 'lang'))
            ->values()
            ->all();
    }
}
