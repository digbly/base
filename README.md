# Laravel CMS

A CMS platform built on Laravel 12. It ships with a modular architecture: features
live in `modules/`, presentation lives in `themes/`, and both are discovered,
activated and booted through their own registries. The front ends are built with
Inertia (React): the admin app is assembled from every enabled module, and the
public site is rendered by the active theme.

## Features

- **Admin dashboard** — users, roles and permissions managed from a single admin.
- **Themes** — full theme packages (`theme.json`, views, assets, translations,
  config, routes), modelled after `nwidart/laravel-modules`.
  The bundled `default` theme renders the public site with its own self-contained
  Inertia (React) front end, built into `public/themes/default` via
  `php artisan theme:build`.
- **Blog module** — posts, categories and comments with translatable content.
- **Auth module** — session login/registration, email verification, password
  reset and profile management, plus social login (Google / Facebook / GitHub)
  via Socialite. Passport is wired up for OAuth2/token infrastructure.
- **Appearance tools** — pages + page blocks, navigation menus, widgets,
  sidebars and a live customizer.
- **Settings & localization** — settings, languages and editable translations.
- **Media library** — powered by `spatie/laravel-medialibrary`.
- **Permissions** — `spatie/laravel-permission`.
- **Audit log** — `spatie/laravel-activitylog`.
- **OpenAPI schema annotations** — resources and form requests are annotated for
  `darkaonline/l5-swagger`.
- **Inertia admin** — a single React app built from `resources/views`, whose
  pages are contributed by each module under `modules/*/resources/views` (for
  example `Admin::dashboard/Index`, `Blog::posts/Index`).

## Tech stack

| Layer | Technology |
| --- | --- |
| Backend | PHP 8.2+, Laravel 12 |
| Modules | `nwidart/laravel-modules` |
| Auth | Laravel Passport, Laravel Socialite |
| Permissions | `spatie/laravel-permission` |
| Media | `spatie/laravel-medialibrary` |
| Translations | `astrotomic/laravel-translatable`, `spatie/laravel-translation-loader` |
| Activity log | `spatie/laravel-activitylog` |
| API schema | `darkaonline/l5-swagger` |
| Admin front end | React 19, Inertia, TypeScript, Tailwind CSS 4, Vite 7, lucide-react, react-hook-form, dnd-kit |
| Theme front end | React 19, Inertia, Tailwind CSS 4, Vite 7 |
| Tests | PHPUnit 11 |
| Code style | Laravel Pint |

## Requirements

- PHP 8.2 or newer with the usual Laravel extensions
- Composer 2
- Node.js 20+ and npm
- A database — SQLite works out of the box; MySQL/PostgreSQL are supported

## Installation

```bash
# 1. Install PHP dependencies
composer install

# 2. Create the environment file and application key
cp .env.example .env
php artisan key:generate

# 3. Run migrations (module migrations are auto-discovered) and seed
php artisan migrate --seed

# 4. Generate the permissions registry
php artisan permission:generate

# 5. Build the admin Inertia front end
npm install
npm run build

# 6. Install the default theme's dependencies and build its front end
cd themes/default && npm install && cd ../..
php artisan theme:build default
```

Then start the application:

```bash
php artisan serve
```

The seeded test user is `test@example.com` (password `password`). Create a super
admin with:

```bash
php artisan make:user --super-admin
```

### Configuration

Key `.env` values:

| Variable | Description |
| --- | --- |
| `APP_URL` | Base URL of the installation |
| `DB_CONNECTION` | `sqlite` (default), `mysql`, `pgsql`, ... |
| `ADMIN_PREFIX` | URL prefix for the admin (default `admin`) |
| `THEME_DEFAULT` | Fallback theme alias (default `default`) |
| `THEMES_ACTIVATOR` | `database` or `file` |
| `MODULES_ACTIVATOR` | `database` (default), `file` or `testing` |
| `GOOGLE_*`, `FACEBOOK_*`, `GITHUB_*` | Social login credentials |

## Development

Run the backend, queue listener and the admin Vite dev server together:

```bash
composer dev
```

The active theme has its own Vite setup and is served separately:

```bash
php artisan theme:build default --dev
```

## Testing

```bash
php artisan test
# or
composer test
```

The test suites are split by module (`AdminModule`, `BlogModule`) plus the
application `Unit` and `Feature` suites in `phpunit.xml`.

## Useful commands

```bash
# Application
php artisan make:user --super-admin       # Create a (super admin) user
php artisan permission:generate           # Sync permissions from the registry

# Modules
php artisan module:list                   # List modules
php artisan module:make <name>            # Scaffold a module

# Themes
php artisan theme:list                    # List themes and their status
php artisan theme:make blog               # Scaffold themes/Blog and enable it
php artisan theme:enable Blog             # Enable a theme
php artisan theme:disable Blog            # Disable a theme
php artisan theme:publish                 # Publish theme assets
php artisan theme:build default           # Build a theme's Inertia front end (Vite)
php artisan theme:build default --dev     # Run a theme's Vite dev server

# Code style
vendor/bin/pint                           # Format PHP (PSR-12)
```

## Project structure

```
app/
  Contracts/ Themes/ Support/              # Menu, page, widget, setting, theme registries
  Modules/                                 # Module registry (repository + activators)
  Http/ Models/ Providers/ ...
modules/
  admin/ auth/ blog/                       # Feature modules
                                           #   routes, migrations, lang, tests and React views
themes/
  default/                                 # Theme packages (theme.json, own Inertia front end)
resources/
  css/                                     # Tailwind entry for the admin front end
  views/                                   # Admin Inertia app (app.tsx), components, lib, auth views
routes/
  web.php  console.php
config/
  modules.php  themes.php  l5-swagger.php
docs/
  the-basics/                              # Settings, menus, widgets, pages
  modules/  themes/                        # Module and theme guides
```

### Modules

Every feature is an `nwidart/laravel-modules` package with its own service
provider, routes (`routes/web.php`), migrations, language files, tests and
Inertia (React) views under `resources/views`. Modules are registered through
their `module.json` manifest. The `Admin` and `Auth` modules are core and always
loaded from `bootstrap/providers.php`; every other module is loaded by
`App\Modules\ModulesServiceProvider` from the activator settings.

### Themes

A theme is a complete package (`theme.json`, service provider, Blade views,
assets, translations, config and routes) living in `themes/`. The registry
mirrors modules so the model is identical: *discover → activate → register →
boot*. The active theme is stored in the settings and applied by the
`ThemeManager`. The bundled `default` theme also ships a self-contained Inertia
front end (`resources/views/app.tsx`, pages, layouts, components) built into
`public/themes/default`.

### Admin front end

The admin is a single Inertia React app:

- The entry point is `resources/views/app.tsx`, rendered by the Blade template
  `resources/views/app.blade.php` and built by the root Vite config.
- Module pages live in `modules/<name>/resources/views` and are referenced by
  their namespaced component name (`Admin::dashboard/Index`,
  `Auth::auth/Login`, `Blog::posts/Index`); `resources/views/lib/inertia-pages.ts`
  resolves them at runtime.
- The public site is the active theme's own Inertia app, not the admin app.

## Documentation

Full documentation lives in [`docs/`](docs/):

- [`docs/modules/information.md`](docs/modules/information.md) — module
  architecture and how modules are built.
- [`docs/themes/information.md`](docs/themes/information.md) — theme
  architecture, commands and SSR.

## License

The Laravel framework is open-sourced software licensed under the
[MIT license](https://opensource.org/licenses/MIT).
