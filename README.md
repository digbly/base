# Laravel CMS

A CMS platform built on Laravel 12. It ships with a modular architecture: features
live in `modules/`, presentation lives in `themes/`, and both are discovered,
activated and booted through their own registries.

## Features

- **Admin dashboard** — users, roles and permissions managed from a single admin.
- **Themes** — full theme packages (`theme.json`, views, assets, translations,
  config, routes), modelled after `nwidart/laravel-modules`.
  The bundled `default` theme renders the public site with its own self-contained
  Inertia (React) front end, built via `php artisan theme:build`.
- **Blog module** — posts, categories and comments with translatable content.
- **Auth module** — session login/registration, email verification, password
  reset, profile management, social login (Google / Facebook / GitHub) via
  Socialite, and an OAuth2 server via Passport.
- **Appearance tools** — pages + page blocks, navigation menus, widgets,
  sidebars and a live customizer.
- **Settings & localization** — settings, languages and editable translations.
- **Media library** — powered by `spatie/laravel-medialibrary`.
- **Permissions** — `spatie/laravel-permission`.
- **Audit log** — `spatie/laravel-activitylog`.
- **API docs** — OpenAPI/Swagger generated with `darkaonline/l5-swagger`.
- **Two React front ends** — an Inertia SSR-style admin rendered from Blade
  (`resources/js`) and a standalone admin SPA (`admin/`) that talks to the JSON
  API.

## Tech stack

| Layer | Technology |
| --- | --- |
| Backend | PHP 8.2+, Laravel 12 |
| Modules | `nwidart/laravel-modules` |
| Auth | Laravel Passport, Laravel Socialite |
| Permissions | `spatie/laravel-permission` |
| Media | `spatie/laravel-medialibrary` |
| Translations | `astrotomic/laravel-translatable`, `spatie/laravel-translation-loader` |
| API docs | `darkaonline/l5-swagger` |
| Inertia admin | React 19, Inertia, TypeScript, Tailwind CSS 4, Vite 7 |
| Admin SPA | React 19, Redux Toolkit, TanStack Query, react-router, i18next, Tailwind CSS 4, Vite 8 |
| Tests | PHPUnit 11, Pest-style module suites |
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

# 5. Build the Inertia admin front end
npm install
npm run build

# 6. Build the default theme's Inertia front end
php artisan theme:build default

# 7. Build the standalone admin SPA
cd admin && npm install && npm run build && cd ..
```

Then start the application:

```bash
php artisan serve
```

The seeded test user is `test@example.com`. Create a super admin with:

```bash
php artisan make:user --super-admin
```

### Configuration

Key `.env` values:

| Variable | Description |
| --- | --- |
| `APP_URL` | Base URL of the installation |
| `DB_CONNECTION` | `sqlite` (default), `mysql`, `pgsql`, ... |
| `ADMIN_PREFIX` | URL prefix for the web admin (default `admin`) |
| `THEME_DEFAULT` | Fallback theme alias (default `default`) |
| `THEMES_ACTIVATOR` | `database` or `file` |
| `GOOGLE_*`, `FACEBOOK_*`, `GITHUB_*` | Social login credentials |
| `FRONTEND_URL` | Base URL of the standalone admin SPA |

## Development

Run the backend, queue listener and root Vite dev server together:

```bash
composer dev
```

The standalone admin SPA is developed separately and proxies `/api` to the
Laravel backend (configurable through `VITE_OAUTH_BASE_URL`):

```bash
cd admin
npm run dev
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
php artisan theme:make blog               # Scaffold and enable a theme
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
  admin/   auth/   blog/                   # Feature modules (own routes, migrations, lang, tests)
themes/
  default/                                 # Theme packages + statuses.json
resources/
  js/                                      # Inertia admin (React 19) + Blade entry
  views/                                   # app.blade.php, auth views
admin/
  src/                                     # Standalone admin SPA (registry-based modules)
routes/
  web.php  api.php  console.php
config/
  modules.php  themes.php  admin-translations.php  l5-swagger.php
docs/
  admin-modules.md  themes.md
```

### Modules

Every feature is an `nwidart/laravel-modules` package with its own service
provider, routes (`routes/web.php`, `routes/api.php`), migrations, language
files and tests. Modules are registered through their `module.json` manifest.

### Themes

A theme is a complete package (`theme.json`, service provider, Blade views,
assets, translations, config and routes) living in `themes/`. The registry
mirrors modules so the model is identical: *discover → activate → register →
boot*. The active theme is stored in the settings and applied by the
`ThemeManager`.

### Admin front ends

- **Inertia admin** (`resources/js`) is rendered by the Blade entry
  (`resources/views/app.blade.php`) for the web routes under `ADMIN_PREFIX`.
- **Admin SPA** (`admin/`) is a standalone React app with its own module
  registry, Redux store and TanStack Query data layer; it consumes the JSON API
  under `/api`.

## Documentation

- [`docs/admin-modules.md`](docs/admin-modules.md) — how to add a feature module
  to the admin SPA.
- [`docs/themes.md`](docs/themes.md) — theme architecture and commands.

## License

The Laravel framework is open-sourced software licensed under the
[MIT license](https://opensource.org/licenses/MIT).
