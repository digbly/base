# Admin Modules

The admin is a **single Inertia (React) application**. There is no standalone
SPA and no JSON API: every module contributes its own backend routes, Inertia
pages, navigation entries and translations, and the shell composes them into one
app.

## How the admin is assembled

- The entry point is `resources/views/app.tsx`, rendered by the Blade root
  template `resources/views/app.blade.php` and built by the root Vite config
  (`vite.config.ts`).
- Shared UI and helpers live in `resources/views`: `components/ui/*` (buttons,
  inputs, modals, tables, ...), `components/NavIcon.tsx`, `components/ThemeProvider.tsx`,
  `hooks/` (`useTranslation`, `useDropdown`, `useFocusTrap`) and `lib/`
  (`route`, `url`, `inertia-form`, `validation`, `theme`, `inertia-pages`).
- Module pages live in `modules/<name>/resources/views` and are referenced by
  their namespaced component name from the controller, for example
  `Inertia::render('Admin::dashboard/Index')`, `'Auth::auth/Login'`,
  `'Blog::posts/Index'`.
- `resources/views/lib/inertia-pages.ts` resolves those names by globbing
  `resources/views/pages/**/*.{tsx,jsx}` (core pages) and
  `modules/*/resources/views/**/*.{tsx,jsx}` (module pages). The namespace
  (`Admin`, `Auth`, `Blog`, ...) is matched case-insensitively against the
  module directory name.
- Vite aliases configure imports: `@` points at `resources/views` and `@modules`
  at `modules/`.

## Directory layout

```
resources/views/
  app.tsx                  # Inertia entry (createInertiaApp)
  components/ui/           # shared design-system components
  components/NavIcon.tsx   # lucide icon registry used by the sidebar
  hooks/                   # useTranslation, useDropdown, useFocusTrap
  lib/                     # route(), url helpers, form/validation, page resolver
  types/                   # SharedProps, NavItem, AuthUser, ...
modules/<name>/resources/views/
  <area>/Index.tsx         # list pages
  <area>/Form.tsx          # create/edit pages
  components/              # module-specific components
  layouts/                 # AdminLayout (admin), AuthLayout (auth)
```

The admin shell lives in `modules/admin/resources/views/layouts/AdminLayout.tsx`
with `components/AdminSidebar.tsx` and `components/UserMenu.tsx`.

## Create a module (example: `reports`)

### 1. Scaffold and wire the module

```bash
php artisan module:make Reports
```

The module gets a service provider (extending
`Nwidart\Modules\Support\ModuleServiceProvider`) and a `RouteServiceProvider`
that maps `routes/web.php`. Add the module to the activator (or enable it) so
`App\Modules\ModulesServiceProvider` boots it. `Admin` and `Auth` are core and
always loaded from `bootstrap/providers.php`.

### 2. Add the routes

`modules/reports/routes/web.php`:

```php
use Illuminate\Support\Facades\Route;
use Modules\Admin\Http\Middleware\RequireAdminPermission;
use Modules\Reports\Enums\Permission;
use Modules\Reports\Http\Controllers\Web\ReportController;

Route::middleware(['auth:web'])
    ->prefix(config('app.admin_prefix', 'admin'))
    ->group(function () {
        Route::prefix('reports')->name('admin.reports.')->group(function () {
            Route::get('/', [ReportController::class, 'index'])
                ->middleware(RequireAdminPermission::class.':'.Permission::View->value)
                ->name('index');
        });
    });
```

### 3. Return an Inertia page from the controller

```php
public function index(Request $request): \Inertia\Response
{
    return Inertia::render('Reports::reports/Index', [
        'title' => __('reports.title'),
        'reports' => ReportResource::collection(
            Report::query()->paginate()
        ),
    ]);
}
```

Validate writes with a `FormRequest` and serialise models with an API
`Resource`, exactly as the other modules do.

### 4. Add the React page

`modules/reports/resources/views/reports/Index.tsx`:

```tsx
import AdminLayout from '@modules/admin/resources/views/layouts/AdminLayout';
import { useTranslation } from '@/hooks/useTranslation';

interface ReportsProps {
    title: string;
    reports: { data: unknown[] };
}

export default function Reports({ title, reports }: ReportsProps) {
    const { t } = useTranslation();

    return (
        <AdminLayout title={title}>
            <h1>{t('reports.title')}</h1>
            {/* feature UI */}
        </AdminLayout>
    );
}
```

Pages are default-exported components. They receive the props passed by the
controller plus the shared props (see below).

### 5. Register the sidebar entry

Navigation is registered on the **backend** (not in the page) from the module
service provider's `boot()`, so a disabled module contributes nothing:

```php
use App\Facades\Menu;
use App\Support\MenuRepository;

Menu::make('reports', fn () => [
    'label' => __('reports.nav.reports'),
    'to' => '/reports',                    // SPA path, relative to the admin prefix
    'icon' => 'file-text',                 // lucide icon name (see NavIcon)
    'permission' => Permission::View->value,
    'position' => MenuRepository::POSITION_ADMIN,
    'priority' => 70,
]);
```

- `to` is a path inside the admin app (no `ADMIN_PREFIX`); the frontend joins it
  with the shared `admin_prefix`.
- An item with a `parent` key becomes a child of that parent (collapsible
  group); the parent usually has no `to`.
- `priority` controls ordering.
- New icons must be added to `resources/views/components/NavIcon.tsx`; unknown
  names fall back to a plain circle.

### 6. Register translations

Register the module's namespace from its service provider so it owns its
strings. When `path` is omitted the convention
`modules/<Studly(namespace)>/resources/lang` is used:

```php
use App\Facades\AdminTranslation;

AdminTranslation::make($this->nameLower, fn (): array => [
    'group' => 'reports',
    'path' => module_path($this->name, 'resources/lang'),
]);
```

Create `modules/reports/resources/lang/en/reports.php` and the matching `vi`
file, then read them in the page with
`const { t } = useTranslation();` and `t('reports.title')` — the first segment is
the namespace.

### 7. Register permissions

Declare permissions in a PHP enum (`modules/reports/app/Enums/Permission.php`)
with a `values()` method, add them to `App\Providers\PermissionServiceProvider`,
and run:

```bash
php artisan permission:generate
```

Use the same string on the route middleware (`RequireAdminPermission`), the menu
item (`permission`) and the enum case.

### 8. Build

```bash
npm run build
```

The page glob is resolved by Vite at build time, so a new page is not available
until the admin front end is rebuilt.

## Shared props

`App\Http\Middleware\HandleInertiaRequests::share()` sends these to every
Inertia response:

| Prop | Description |
| --- | --- |
| `auth.user` | Current user (`id`, `name`, `email`, `avatar_url`, `is_super_admin`, `permissions`) or `null` |
| `flash` | `success`, `error`, `warning` from the session |
| `admin_menu` | The permission-filtered sidebar tree (`NavItem[]`) |
| `admin_prefix` | Value of `ADMIN_PREFIX` (default `admin`) |
| `locale` | Current application locale |
| `translations` | Translation namespaces keyed by frontend namespace |
| `routes` | Named Laravel routes keyed by name, exposed to `route()` |

## Frontend helpers

- `route(name, params?)` (`resources/views/lib/route.ts`) builds a URL from the
  shared `routes` prop: `route('admin.blog.posts.index')`,
  `route('admin.blog.posts.destroy', { post: id })`. Placeholders use `{param}`;
  extra keys become a query string. It is also exposed globally as
  `window.route`.
- `useTranslation()` (`resources/views/hooks/useTranslation.ts`) resolves
  `'<namespace>.<key.path>'` from the shared `translations` prop and accepts an
  optional fallback: `t('admin.nav.dashboard', 'Dashboard')`.
- `AdminLayout` reads `admin_menu`, `admin_prefix`, `flash` and `auth.user`,
  renders the sidebar/topbar, and accepts a `title` prop.

## Navigation contract

The sidebar tree is built server-side by `Menu::tree('admin')` and filtered in
`HandleInertiaRequests::adminMenu()` against the user's permissions (super
admins see everything). The serialised item shape (`NavItem`):

```ts
interface NavItem {
    key: string;
    label: string;               // already translated for the request
    to: string | null;           // SPA path (no admin prefix), null for groups
    icon: string | null;         // lucide icon name, mapped in NavIcon.tsx
    permission: string | null;
    children: NavItem[];
}
```

`AdminSidebar` renders this tree and highlights the active item from the current
Inertia URL.

## Permissions

Authorization is enforced on the **backend**; the frontend only mirrors it for
usability.

- The catalog is declared in code through module permission enums
  (`Modules\Auth\Enums\Permission`, `Modules\Admin\Enums\*Permission`,
  `Modules\Blog\Enums\Permission`, ...), registered in
  `App\Providers\PermissionServiceProvider` via `App\Support\PermissionRegistry`.
  Run `php artisan permission:generate` to persist them as Spatie permissions.
- Web routes are guarded per action by `RequireAdminPermission` (e.g.
  `RequireAdminPermission::class.':'.Permission::View->value`).
- Controllers expose per-action `abilities` (via the `AuthorizesAdmin` trait)
  so pages can hide controls the user cannot use.
- `User::isSuperAdmin()` (`users.is_super_admin`) bypasses every check.
- `User::permissionNames()` returns the Spatie permission names, or `['*']` for
  super admins.
- `admin_menu` is already filtered by permission server-side.

When a module adds a permission, add it to its enum, register it in
`PermissionServiceProvider`, and run `permission:generate`. Use the same string
on the route, the menu item and the enum case.

## i18n

The frontend loads its strings at runtime from the backend, one namespace per
owner. `HandleInertiaRequests` shares a `translations` prop keyed by namespace;
`App\Support\AdminTranslations` builds it from the namespaces registered through
the `AdminTranslation` registry. Each owner registers its own namespace from its
service provider, so adding a module never requires editing a central file:

| Namespace | Backend group | Stored in |
| --- | --- | --- |
| `common` | `common` | `resources/lang/{en,vi}/common.php` (shell + auth layout) |
| `admin` | `admin` | `modules/admin/resources/lang/{en,vi}/admin.php` (also holds backend menu labels) |
| `auth` | `admin_auth` | `modules/auth/resources/lang/{en,vi}/admin_auth.php` |
| `blog` | `blog` | `modules/blog/resources/lang/{en,vi}/blog.php` |

`registerNamespaces()` exposes each module's language directory as a translation
namespace; because a module provider only boots when the module is enabled, a
disabled module contributes no namespace or locale.

## Conventions and gotchas

- There is no `admin/` SPA and no `/api` admin layer. Pages come from
  `modules/*/resources/views`; controllers return `Inertia::render(...)` and
  redirects, not JSON resources.
- Inertia page components are **default exports**.
- Admin route names are prefixed `admin.` and grouped under
  `config('app.admin_prefix')`; the frontend builds URLs from those names with
  `route()`.
- Keep module pages free of cross-module imports except for the shared shell
  (`AdminLayout`) and shared UI under `@/components`.
- Do not add translation JSON to the frontend. Strings live in the backend:
  the shared shell in `resources/lang/{en,vi}/common.php`, and each module's
  own strings in that module's `resources/lang/` directory.
- New sidebar icons must be registered in
  `resources/views/components/NavIcon.tsx`.
- Name module pages `<area>/Index.tsx` and `<area>/Form.tsx` for consistency
  with the existing admin and blog modules.

## Verify

```bash
npm run build          # build the admin front end (tsc via Vite)
php artisan test       # AdminModule / BlogModule suites
vendor/bin/pint        # PHP code style
```

## Checklist

- [ ] Module scaffolded and enabled so its service provider boots
- [ ] `routes/web.php` maps the module's admin routes under the admin prefix
- [ ] Controller returns `Inertia::render('<Module>::<area>/<Page>', [...])`
- [ ] `FormRequest` + `Resource` used for writes and serialisation
- [ ] React page added under `modules/<name>/resources/views/<area>/` with a default export
- [ ] Sidebar item registered via `Menu::make()` with `to`, `icon`, `permission` and `priority`
- [ ] New icon (if any) added to `resources/views/components/NavIcon.tsx`
- [ ] Namespace registered via `AdminTranslation::make()`; `resources/lang/{en,vi}/<group>.php` added
- [ ] New permission (if any) added to a permission enum and registered in `PermissionServiceProvider`, then `php artisan permission:generate`
- [ ] `npm run build` and `php artisan test` pass
