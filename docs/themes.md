# Themes

Multiple themes, built on the same architecture as
`nwidart/laravel-modules`. A theme is a full package: it has a manifest
(`theme.json`), a service provider, views, assets, translations, config and
routes. Themes live in `themes/`.

The registry is deliberately a structural mirror of modules so the mental model
is identical: *discover → activate → register → boot*.

## Directory layout

```
themes/
  <theme>/
    theme.json
    composer.json         # PSR-4 "Themes\\<Theme>\\" => "app/", merged via composer-merge-plugin
    app/Providers/ThemeServiceProvider.php
    config/config.php
    resources/
      views/            # Blade views + optional Inertia (React) pages/components
      assets/           # css/js/img, published to public/themes/<theme>
      lang/             # namespaced translations
    routes/web.php
  statuses.json         # activation statuses (file activator only)
```

Core classes:

```
app/
  Themes/
    Theme.php                    # package object (≈ Nwidart\Modules\Module)
    FileRepository.php           # scans themes (≈ Nwidart\Modules\FileRepository)
    FileActivator.php            # statuses.json (≈ Nwidart\Modules\FileActivator)
    DatabaseActivator.php        # active theme name stored in settings
    ThemeManager.php             # resolves + activates the active theme
    ThemesServiceProvider.php    # container bindings + boot
    Exceptions/ThemeNotFoundException.php
  Contracts/ThemeActivator.php
config/themes.php
```

## Mapping to laravel-modules

| Modules | Themes |
| --- | --- |
| `modules/<Name>/module.json` | `themes/<Theme>/theme.json` |
| `modules/<Name>/composer.json` | `themes/<Theme>/composer.json` |
| `Nwidart\Modules\Module` | `App\Themes\Theme` |
| `Nwidart\Modules\FileRepository` | `App\Themes\FileRepository` |
| `Nwidart\Modules\Activators\FileActivator` | `App\Themes\FileActivator` |
| settings-backed module activator (`plugin_statuses`) | `App\Themes\DatabaseActivator` (`theme`) |
| `modules/statuses.json` | `themes/statuses.json` |
| `RepositoryInterface` (bound as `modules`) | `FileRepository` (bound as `themes`) |
| `module_path()` | `theme_path()` |

Like modules, each theme is a composer package: its `composer.json` maps
`"Themes\\<Theme>\\": "app/"` and is pulled in by
`wikimedia/composer-merge-plugin` via the root `extra.merge-plugin.include`
patterns (`modules/*/composer.json`, `themes/*/composer.json`). PHP classes
therefore live under `themes/<Theme>/app/`, and autoloading requires
`composer dump-autoload` after a theme is added — which `theme:make` runs for
you. `theme.json` reuses the same `Json` reader from the modules package
(`Nwidart\Modules\Json`).

## Commands

```bash
php artisan theme:list                 # all themes + status
php artisan theme:list --only=enabled  # filter by status
php artisan theme:make blog            # scaffold themes/Blog + enable it
php artisan theme:make blog --disabled # scaffold without enabling
php artisan theme:make blog --force    # overwrite an existing theme
php artisan theme:make blog --no-dump  # skip composer dump-autoload
php artisan theme:enable Blog
php artisan theme:disable Blog
php artisan theme:publish              # copy every theme's resources/assets
php artisan theme:publish Blog --force # clean + republish one theme
php artisan theme:build default        # build a theme's Inertia front end (Vite)
php artisan theme:build default --dev  # run the theme's Vite dev server
php artisan theme:ssr default          # start the theme's Inertia SSR server
```

`theme:make` scaffolds a full Inertia (React) front end: `theme.json`,
`composer.json`, `package.json`, `vite.config.js`, `tsconfig.json`,
`app/Providers/ThemeServiceProvider.php`, `resources/views/theme.blade.php`,
`resources/views/app.tsx`, a server-side-rendering entry
`resources/views/ssr.tsx`, the client helpers (`lib/resolve-page.ts`,
`lib/route.ts`), a `pages/Home.tsx`, `config/config.php`, `routes/web.php` and
`resources/assets/css/app.css`. It then runs `composer dump-autoload` so the new
theme's PSR-4 mapping is registered. Stubs live in `resources/stubs/themes`.

## theme.json

```json
{
    "name": "Default",
    "alias": "default",
    "description": "Default theme",
    "version": "1.0.0",
    "priority": 0,
    "providers": [
        "Themes\\Default\\Providers\\ThemeServiceProvider"
    ],
    "aliases": {},
    "files": []
}
```

- `name` is the identity used by the activator (the active theme name stored
  in settings, or `themes/statuses.json` with the file activator).
- `alias` is the lowercase key used for namespaces, config and helpers. Falls
  back to `name` when omitted.
- `priority` orders registration (`FileRepository::getOrdered()`).
- `providers` are resolved through Laravel's `ProviderRepository` with a
  per-theme cached services file (`<alias>_theme.php`).
- `views`, `assets`, `lang`, `config`, `routes` may override the default
  resource paths from `config/themes.php`.

## Registration flow

`App\Themes\ThemesServiceProvider` is registered after `AppServiceProvider`
in `bootstrap/providers.php`.

1. **register()** — merges `config/themes.php`, binds `ThemeActivator`,
   `FileRepository` (alias `themes`) and `ThemeManager` as singletons, then
   calls `FileRepository::register()` for every enabled theme. Each `Theme`
   registers its aliases, providers and include files.
2. **boot()** — calls `FileRepository::boot()` (files-on-boot mode), then
   `ThemeManager::activate()` which resolves the active theme and installs its
   views, config, translations and routes.

Code-level registration (`register()` / `boot()`) runs for **every enabled
theme**; render-level registration (views, config, translations, routes) runs
for the **active theme only**, so themes cannot bleed into each other's output.

The active theme is resolved once during boot by `ThemeManager::activate()`.

## Theme selection

`ThemeManager::resolve()` picks the first enabled candidate:

1. an explicit theme passed to `resolve()` / `activate()`,
2. `config('themes.default')` (`THEME_DEFAULT`, default `default`),

then falls back to the first enabled theme, or `null` when no theme is enabled.

```php
app(App\Themes\ThemeManager::class)->activate('another');
```

In console (no request) resolution falls back to the default theme.

## Activation

The default **database** activator stores the active theme name in the settings
(`theme` key), so **only one theme can be active at a time**. A theme is enabled
only when it is the stored active theme; every other theme is disabled.

```php
app('themes')->findOrFail('Default')->enable();   // sets the active theme
app('themes')->findOrFail('Another')->disable();  // clears it when it matches
app('themes')->allEnabled();                      // at most one theme
```

The **file** activator (`FileActivator`) keeps the legacy behaviour: statuses
are stored in `themes/statuses.json` and a theme not listed is disabled. Select
it with `THEMES_ACTIVATOR=file` or `themes.activator => 'file'`.

```json
{
    "Default": true,
    "Another": false
}
```

## View resolution and precedence

The active theme is installed in two ways:

- `View::addNamespace('<alias>', <views>)` — renders `default::welcome`.
- `View::getFinder()->prependLocation(<views>)` — transparently overrides
  application views (`view('welcome')`).

Precedence is intentionally **Theme > App**. Module views are namespaced
(`auth::...`) and are never touched, so a theme cannot override a module. This
keeps modules isolated and prevents a theme from breaking a module's internal
templates.

```
view('welcome')      → theme default, else app
view('default::x')   → theme default only
view('auth::login')  → module auth only
```

A theme that needs to override a module must do so deliberately, e.g. by
registering the module's namespace path from its own provider.

## Helpers

```php
theme();                       // ?App\Themes\Theme — active theme
theme_name();                  // ?string — active alias (falls back to default)
theme_path('default', 'resources/views');
theme_asset('css/app.css');    // http://host/themes/default/css/app.css
```

## Assets

Theme assets are served from `public/themes/<alias>` (URL prefix configurable
via `themes.paths.assets_url`). The source of truth is
`themes/<theme>/resources/assets`; run `theme:publish <Name>` to copy it into
`public/themes/<alias>` (use `--force` to clean the destination first).

## Inertia front end

A theme may ship its own self-contained Inertia (React) front end instead of
Blade pages. The bundled `default` theme is the reference implementation:

```
themes/default/
  vite.config.js                 # laravel + react + tailwind, buildDirectory themes/default
  package.json                   # vite / react / @inertiajs/react
  tsconfig.json
  resources/
    views/
      theme.blade.php            # Inertia root, guarded @vite('resources/views/app.tsx', 'themes/default')
      app.tsx                    # createInertiaApp entry (client)
      ssr.tsx                    # Inertia SSR server entry
      lib/resolve-page.ts        # resolves pages/**/*.tsx
      lib/route.ts               # SSR-safe route() helper
      pages/                     # Home, Category, Post, Search, NotFound
      layouts/ components/       # layout, sidebar, widgets, blocks, comments
    assets/css/app.css           # Tailwind entry (imported by app.tsx)
```

Like module views, the theme keeps its React pages and components alongside its
Blade templates under `resources/views` (`pages/**/*.tsx`). Blade only compiles
`.blade.php`, so the two coexist safely.

- Controllers return `Inertia::render('Home', [...])` and call
  `->rootView('default::theme')` so the theme renders its own root template.
  The theme root view uses a distinct name (`theme.blade.php`) so it never
  shadows the application's `app` Inertia root view.
- Build with `php artisan theme:build default` (or `--dev` for the Vite dev
  server). Assets land in `public/themes/default` from the theme's own Vite
  config; the root template only injects them when the manifest exists.
- Page blocks and widgets registered with a `component` (and an optional
  `data` resolver) are resolved to a JSON payload by `SidebarRenderer::payload()`
  and `PageBlockRenderer`, and rendered client-side.

## Server-side rendering

Each theme may ship its own SSR bundle and server. A theme opts in through the
`ssr` key in its `theme.json`:

```json
{
    "ssr": {
        "enabled": true,
        "host": "127.0.0.1",
        "port": 13714,
        "bundle": "bootstrap/ssr/themes/default/ssr.js"
    }
}
```

Only the active theme's configuration is applied, and only on theme routes: the
`App\Http\Middleware\ThemeSsr` middleware (attached to every theme route by
`Theme::registerRoutes()`) points Inertia's SSR gateway at the active theme via
`Theme::registerSsr()`. The application's own Inertia pages are never affected.

- `enabled` — opt the theme out of SSR.
- `host` / `port` — where the theme's SSR server listens and where Inertia
  dispatches. Give every theme its own port if you run several at once.
- `bundle` — bundle path, absolute or relative to the project root. Defaults to
  `<themes.ssr.output>/<alias>/ssr.js`.

Build the client and SSR bundles with `theme:build` (the theme's `build` script
runs `vite build && vite build --ssr`), then start the server:

```bash
php artisan theme:build default
php artisan theme:ssr default
```

`theme:ssr` spawns `node bootstrap/ssr/themes/default/ssr.js` with `SSR_PORT`
and `SSR_HOST` set from the theme's `ssr.port` / `ssr.host`. The SSR entry
(`resources/views/ssr.tsx`) reads them and defaults to `13714` on `127.0.0.1`,
so the server binds to the loopback interface unless a theme opts otherwise.

Global defaults and the master switch live in `config/themes.php` under `ssr`:

- `ssr.enabled` (`THEME_SSR_ENABLED`) — when `false`, theme SSR is disabled
  regardless of `theme.json`; unset respects `inertia.ssr.enabled`.
- `ssr.host` (`THEME_SSR_HOST`) — default host.
- `ssr.output` — base directory for built per-theme bundles.

A theme that declares no `ssr` block still enables SSR by default; if its bundle
does not exist, Inertia simply falls back to client-side rendering.

## Configuration

`config/themes.php`:

- `default` — fallback theme alias.
- `current` — set at runtime by `ThemeManager` (read-only).
- `composer.vendor` — vendor name used by `theme:make` for the generated package.
- `paths.themes` / `paths.assets` / `paths.assets_url`.
- `paths.generator.*` — default resource subfolders.
- `scan` — additional theme roots (e.g. `vendor/*/*`).
- `register.translations` / `register.files`.
- `activator` — selects which activator to use (default `database`,
  `THEMES_ACTIVATOR`).
- `activators.<name>.class` — activator definitions. `activators.file.statuses-file`
  points to `themes/statuses.json`; `activators.database.key` (default `theme`)
  is the settings key that holds the active theme name.

## Create a theme

```bash
php artisan theme:make blog
```

Or manually:

1. Create `themes/<Studly>/theme.json` with `name` and `alias`.
2. Add `themes/<Studly>/composer.json` mapping `"Themes\\<Studly>\\": "app/"`,
   then add `app/Providers/ThemeServiceProvider.php` and list its class in
   `providers`.
3. Put Blade templates in `resources/views`, assets in `resources/assets`,
   translations in `resources/lang`, config in `config`, routes in `routes`.
4. Enable it: run `theme:enable <Name>` (the default database activator stores
   it as the active theme; with the file activator, add
   `"<Name>": true` to `themes/statuses.json`).
5. Run `composer dump-autoload` and select it via `THEME_DEFAULT`.

`theme_path('default', 'resources/views/theme.blade.php')` is handy while
scaffolding, and `theme:publish <Name>` copies assets into
`public/themes/<alias>`.

## Gotchas

- Theme aliases share the view-namespace registry with modules; avoid aliases
  that collide with a module lower name (e.g. `auth`).
- A theme's `config/config.php` is merged under the alias as a config key, so an
  alias matching a core config file (e.g. `app`, `database`) would overwrite it.
  Keep aliases unique and non-core.
- Theme routes (`routes/web.php`) are loaded only for the active theme, under
  the `web` middleware group.
- Unlisted themes are disabled; a fresh theme will not render until enabled.
- `ThemeManager::activate()` can be re-run per request to preview a theme;
  view locations are prepended, so repeated activation within one process keeps
  the last active theme on top.
- Config caches: run `php artisan config:clear` after editing
  `config/themes.php`.

## Verify

```bash
php artisan config:clear
php artisan about
php artisan tinker --execute="var_dump(app('themes')->allEnabled(), theme_name());"
```

## Relationship with modules

Modules and themes are independent registries. Modules provide backend features
and namespaced views/routes; themes provide the public presentation layer. A module view is always resolved from its own namespace, so adding a
theme never changes module behavior. This is the isolation guarantee that keeps
the two systems composable.
