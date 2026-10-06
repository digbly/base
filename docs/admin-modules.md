# Admin Modules

The admin SPA lives in `admin/` and is built as a **single app with a module
registry**. Each feature module owns its routes, sidebar entries, and
translations. The shell (`AdminLayout`) and the registry (`src/app`) stay
feature-agnostic.

## Directory layout

```
admin/src/
  app/
    types.ts        # AdminModule
    registry.ts     # registerModules(), getAdminRoutes(), getPublicRoutes()
    routes.tsx      # builds the RouteObject[] via useRoutes()
    modules.ts      # registers every module (imported for side effects)
  modules/
    auth/
      module.tsx    # publicRoutes only (login/register/...)
      lazy.ts       # React.lazy wrappers (kept out of module.tsx)
      layout/AuthLayout.tsx
      views/*.tsx
    dashboard/
      module.tsx    # nav + routes
      lazy.ts
      views/DashboardView.tsx
    users/ settings/ ...
  components/       # shared UI, layout shell, route guards
  store/ utils/     # shared state and helpers
```

## Create a module (example: `reports`)

### 1. Create the view

`admin/src/modules/reports/views/ReportsView.tsx`

```tsx
export const ReportsView = () => {
  return <div>{/* feature UI */}</div>;
};
```

### 2. Add a lazy wrapper

`module.tsx` must not declare components (the `react-refresh` lint rule flags
files that both export config and define components). Put `React.lazy` here:

`admin/src/modules/reports/lazy.ts`

```ts
import { lazy } from 'react';

export const ReportsView = lazy(() =>
  import('./views/ReportsView').then((module) => ({ default: module.ReportsView }))
);
```

### 3. Add translations

The admin SPA has no bundled translations: every string is served by the
backend from the owning module's `resources/lang` directory. Register the
module's i18next namespace from its service provider so it owns its strings.

1. Register the namespace in the module service provider:

```php
use App\Facades\AdminTranslation;

AdminTranslation::make('reports', fn (): array => [
    'group' => 'reports',
    // Optional; defaults to modules/<Studly(namespace)>/resources/lang.
    'path' => module_path('Reports', 'resources/lang'),
]);
```

2. Create the language files in the owning module:

```php
// modules/reports/resources/lang/en/reports.php
return [
    'title' => 'Reports',
];
```

```php
// modules/reports/resources/lang/vi/reports.php
return [
    'title' => 'Báo cáo',
];
```

3. Load it in the view with `const { t } = useTranslation();` and use
   `t('reports.title')`. Shared shell strings fall back to the `common`
   namespace, so they need no prefix.

A module that only adds pages to the existing admin shell can instead add keys
under the `admin` group in `modules/admin/resources/lang/{en,vi}/admin.php` and use
`t('admin.pages.title')`.

### 4. Describe the module

`admin/src/modules/reports/module.tsx`

```tsx
import type { AdminModule } from '../../app/types';
import { ReportsView } from './lazy';

export const reportsModule: AdminModule = {
  routes: [
    {
      path: '/reports',
      element: <ReportsView />,
      handle: { permission: 'reports.view' },
    },
  ],
};
```

The sidebar entry is **not** declared here. It is registered on the backend
through the `Menu` repository (see "Navigation and titles").

### 5. Register the module

`admin/src/app/modules.ts`

```ts
import { reportsModule } from '../modules/reports/module';

registerModules([authModule, adminModule, blogModule, reportsModule]);
```

That is all: the route, the sidebar entry and the topbar title are wired
automatically; translations come from the namespace declared earlier.

## Module contract

Defined in `admin/src/app/types.ts`:

```ts
interface AdminModule {
  routes?: RouteObject[];        // inside ProtectedRoute + AdminLayout
  publicRoutes?: RouteObject[];  // outside the admin shell (e.g. auth pages)
}
```

All fields are optional, so `auth` only provides `publicRoutes`, while feature
modules provide `routes` (and optionally `standaloneRoutes`). Translations are
not part of the module contract; they are served by the backend namespaces.

The sidebar navigation has its own contract, returned by the backend
(`admin/src/types/navigation.ts`):

```ts
interface NavigationItem {
  id: string;
  label: string;        // already translated by the API
  to: string | null;    // SPA path (no website prefix), null for groups
  icon: string;         // lucide icon name, mapped in utils/navIcons.ts
  permission: string | null;
  children: NavigationItem[];
}
```

## Routing and lazy loading

`src/app/routes.tsx` composes the tree. Admin routes are nested under
`ProtectedRoute > AdminLayout`; public routes are top-level:

```tsx
const routes: RouteObject[] = [
  ...getPublicRoutes(),
  {
    element: <ProtectedRoute><AdminLayout /></ProtectedRoute>,
    children: getAdminRoutes(),
  },
  { path: '/', element: <Navigate to="/dashboard" replace /> },
  { path: '*', element: <Navigate to="/auth/login" replace /> },
];
return useRoutes(routes);
```

Every view is `React.lazy`, so it ships as its own chunk. Suspense boundaries
are provided by the shell, which keeps the sidebar/topbar visible while a page
loads:

- `AdminLayout` wraps `<Outlet />` in `<Suspense fallback={<PageLoader />}>`.
- `AuthLayout` wraps its `<Outlet />`.
- `/auth/callback` is wrapped in `auth/module.tsx`.

Do not add a top-level Suspense around `AppRoutes`; it would unmount the shell
during navigation.

## Navigation and titles

The website-admin sidebar is **dynamic**: the frontend fetches it from
`GET /api/v1/admin/navigation`. It is not declared in the
frontend modules.

Backend registration (in the owning module's service provider, e.g.
`Modules\Blog\Providers\BlogServiceProvider` for a blog item, or
`Modules\Admin\Providers\AdminServiceProvider` for core admin items):

```php
Menu::make('reports', fn () => [
    'label' => __('admin.nav.reports'),   // literal label, translated per request
    'to' => '/reports',                   // SPA path, no website prefix
    'icon' => 'file-bar-chart',           // lucide icon name
    'permission' => 'reports.view',
    'position' => MenuRepository::POSITION_ADMIN,
    'priority' => 70,
]);
```

- An item with a `parent` key becomes a child of that parent (collapsible group);
  the parent item itself usually has no `to`.
- Registering in the owning module means a disabled module contributes no
  sidebar items.
- `priority` controls ordering. Labels live in the owning module's language
  file (`modules/admin/resources/lang/{en,vi}/admin.php`, `modules/blog/resources/lang/{en,vi}/blog.php`)
  and follow the request `Accept-Language` header.
- The route stays in the frontend module (`routes` + `handle.permission`); the
  menu only controls what the sidebar shows.

Frontend consumption:

- `AdminSidebar` renders `useGetNavigationQuery()`, filtered by
  `state.auth.user.permissions` (`utils/navigation.ts` + `utils/permission.ts`).
- `AdminTopbar` resolves the title from the same navigation via
  `resolveNavigationTitle(pathname)`, so detail/form pages inherit the section
  title. New icons must be added to `admin/src/utils/navIcons.ts`.

## Permissions

Permission checks are split between the API (the real boundary) and the UI
(usability).

Frontend:

- `NavigationItem.permission` hides the sidebar entry when the user lacks it.
- `route.handle.permission` is read by `RequirePermission` (via `useMatches`,
  deepest match wins) and renders `ForbiddenView` when access is denied.
- `admin/src/utils/permission.ts` treats **unknown** permissions as allowed so
  a not-yet-loaded profile never blocks the UI. The API remains authoritative.

Backend contract (`modules/auth`):

- The permission catalog is declared in code via the module permission enums
  (`Modules\Auth\Enums\Permission`, `Modules\Admin\Enums\*`,
  `Modules\Blog\Enums\Permission`, ...), then registered in
  `App\Providers\PermissionServiceProvider` through `App\Support\PermissionRegistry`.
  Run `php artisan permission:generate` to persist the catalog as Spatie
  permissions (the provider itself never writes to the database).
- Permission resolution is lenient: assigning a permission that has not been
  generated yet is skipped instead of throwing, so a missing
  `permission:generate` run never breaks the app.
- Spatie is the single source of truth for authorization: roles are dynamic
  (admin-defined) and users get permissions through their roles.
- `User::isSuperAdmin()` (column `users.is_super_admin`) bypasses every check
  via a `Gate::before` hook.
- `User::permissionNames()` returns the Spatie permission names, or `['*']` for
  super admins.
- `UserResource` returns `permissions`, `roles` and `is_super_admin` in every
  user payload.

If a module introduces a new permission, add it to the relevant enum
(`Permission`, `MenuPermission`, `PagePermission`) and use the same string on
the backend menu item and `handle.permission`; register it in
`PermissionServiceProvider` and run `permission:generate`.
When the module adds admin-only API endpoints, enforce the permission
server-side as well (e.g. `permission:users.manage`) — the UI guard
is not authorization.

## i18n

The admin SPA loads its strings at runtime from the backend, one i18next
namespace per module. `HandleInertiaRequests` shares a `translations` prop on
every Inertia response, keyed by namespace; `App\Support\AdminTranslations`
builds it from the namespaces registered through the `AdminTranslation`
registry. Each owner registers its own namespace from its service provider —
the application shell registers `common`, each module registers its own — so a
new module never requires editing a central configuration file:

| i18next namespace | Backend group | Stored in |
| ----------------- | ------------- | --------- |
| `common`          | `common`      | `resources/lang/{en,vi}/common.php` (shell + auth layout) |
| `admin`           | `admin`       | `modules/admin/resources/lang/{en,vi}/admin.php` (also holds the backend navigation labels) |
| `auth`            | `admin_auth`  | `modules/auth/resources/lang/{en,vi}/admin_auth.php` |
| `blog`            | `blog`        | `modules/blog/resources/lang/{en,vi}/blog.php` |

`App\Support\AdminTranslations` resolves each namespace to its backend group
and owning module. `registerNamespaces()` exposes module directories as
translation namespaces; because a module's provider only boots when the module
is enabled, a disabled module contributes no namespace or locale.

Components call `useTranslation()` and reference the namespace inline with a
dot: the first segment is the namespace (`t('blog.posts.title')`). Keys without
a namespace prefix resolve against `common` for shared shell strings:

```
common:  topbar.*, userMenu.*, role.*, forbidden.*, comingSoon,
         brandDesc, version, layout.*
admin:   nav.* (also read by the backend menu), dashboard.*, users.*,
         settings.*, media.*, menus.*, widgets.*, customize.*, pages.*
auth:    login.*, register.*, forgotPassword.*, resetPassword.*, verifyEmail.*, oauth.*
blog:    nav.* (also read by the backend menu), posts.*, categories.*,
         comments.*, pagination.*
```

Cross-namespace references are explicit: the blog post form uses
`t('blog.posts.title')` for its own strings and `t('admin.media.insertImage')`
for the shared media picker.

Locales are discovered from the directories in `resources/lang/` and every
owning module's `resources/lang/`; the frontend is limited to the codes listed in
`supportedLngs` (`['en', 'vi']`). Unknown locales or namespaces return `404`.

The `nav.*` labels live in the `admin`/`blog` namespaces and are read by both
sides: the backend menu uses `__('admin.nav.dashboard')` /
`__('blog.nav.blogPosts')`, and the shell uses `t('admin.nav.dashboard')` /
`t('admin.nav.menuLabel')`.

## Conventions and gotchas

- Keep `module.tsx` free of component definitions; put `React.lazy` in `lazy.ts`.
- Admin route paths are absolute (`/reports`) and rendered inside the shell.
- One route entry per page; group a module's pages under a path prefix when it
  grows (e.g. `/reports`, `/reports/settings`).
- Backend modules should namespace their API routes per module
  (`api/v1/reports/...`) to avoid collisions.
- Do not add translation JSON to the frontend. Strings live in the backend:
  the shared shell in `resources/lang/{en,vi}/common.php`, and each module's
  own strings in that module's `resources/lang/` directory. Reference them with
  `useTranslation()` + `t('<namespace>.<key>')`.

## Verify

```bash
cd admin
npm run lint     # 0 errors
npm run build    # tsc -b + vite build
```

Backend, when permissions/endpoints change:

```bash
php artisan test tests/Unit/Auth tests/Feature/Auth
```

## Checklist

- [ ] `modules/<name>/views/XxxView.tsx` created
- [ ] `modules/<name>/lazy.ts` exports the lazy component
- [ ] Namespace registered via `AdminTranslation::make()` in the owning module's service provider; strings added to `resources/lang/{en,vi}/<group>.php`
- [ ] `modules/<name>/module.tsx` exports the `AdminModule`
- [ ] `src/app/modules.ts` registers the module
- [ ] Sidebar item registered in the owning module's service provider via `Menu::make()` with label, `to`, icon and permission
- [ ] Label added to the owning module's language file; icon added to `src/utils/navIcons.ts` if new
- [ ] New permission (if any) added to a backend permission enum, registered in `PermissionServiceProvider` + run `php artisan permission:generate`
- [ ] `handle.permission` on the route and `permission` on the menu item match the backend string
- [ ] `npm run lint` and `npm run build` pass
