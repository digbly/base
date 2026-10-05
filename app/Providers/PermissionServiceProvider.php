<?php

namespace App\Providers;

use App\Support\PermissionRegistry;
use Illuminate\Support\ServiceProvider;
use Modules\Admin\Enums\LanguagePermission;
use Modules\Admin\Enums\MediaPermission;
use Modules\Admin\Enums\MenuPermission;
use Modules\Admin\Enums\PagePermission;
use Modules\Admin\Enums\ThemePermission;
use Modules\Admin\Enums\WidgetPermission;
use Modules\Auth\Enums\Permission as AuthPermission;
use Modules\Blog\Enums\Permission as BlogPermission;

class PermissionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PermissionRegistry::class);
    }

    public function boot(): void
    {
        $this->app->make(PermissionRegistry::class)->register([
            ...MenuPermission::values(),
            ...AuthPermission::values(),
            ...BlogPermission::values(),
            ...MediaPermission::values(),
            ...LanguagePermission::values(),
            ...WidgetPermission::values(),
            ...PagePermission::values(),
            ...ThemePermission::values(),
        ]);
    }
}
