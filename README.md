# Laravel CMS

A CMS platform built on Laravel 12. It ships with a modular architecture: features
live in `modules/`, presentation lives in `themes/`, and both are discovered,
activated and booted through their own registries. The front ends are built with
Inertia (React): the admin app is assembled from every enabled module, and the
public site is rendered by the active theme.

## Features

- **Admin dashboard** — users, roles and permissions managed from a single admin.
- **Themes** — full theme packages (`theme.json`, views, assets, translations,
  config, routes), modelled after `nwidart/laravel-modules`. The bundled
  `default` theme renders the public site with its own self-contained Inertia
  (React) front end.
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
- **Inertia admin** — a single React app whose pages are contributed by each
  enabled module and composed into one shell.

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

## Documentation

- **The Basics**
  - [Settings](docs/the-basics/settings.md)
  - [Theme Settings](docs/the-basics/theme-settings.md)
  - [Translation](docs/the-basics/translation.md)
  - [Permissions](docs/the-basics/permissions.md)
  - [Admin Menus](docs/the-basics/menus.md)
  - [Navigation Menus](docs/the-basics/navigation-menus.md)
  - [Widgets & Sidebars](docs/the-basics/widgets.md)
  - [Pages, Templates & Blocks](docs/the-basics/pages.md)
- **Modules**
  - [Information](docs/modules/information.md)
  - [Make CRUD](docs/modules/crud.md)
  - [Routing](docs/modules/routing.md)
  - [Helpers](docs/modules/helpers.md)
  - [Commands](docs/modules/commands.md)
- **Themes**
  - [Information](docs/themes/information.md)
  - [Asset Compilation](docs/themes/assets.md)
  - [Theme Commands](docs/themes/commands.md)
  - [Theme Helpers](docs/themes/helpers.md)
  - [Theme Configs](docs/themes/settings.md)
  - [Nav Menus](docs/themes/menus.md)
  - [Templates & Blocks](docs/themes/templates.md)
  - [Widgets](docs/themes/widgets.md)

## License

The Laravel framework is open-sourced software licensed under the
[MIT license](https://opensource.org/licenses/MIT).
