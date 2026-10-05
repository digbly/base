<?php

use App\Themes\DatabaseActivator;
use App\Themes\FileActivator;

return [

    /*
    |--------------------------------------------------------------------------
    | Composer Vendor
    |--------------------------------------------------------------------------
    |
    | The vendor name used by theme:make when generating a theme composer.json.
    |
    */

    'composer' => [
        'vendor' => env('THEME_VENDOR', 'juzaweb'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Theme
    |--------------------------------------------------------------------------
    |
    | The theme alias used when a website does not define its own theme or when
    | the configured theme is missing/disabled.
    |
    */

    'default' => env('THEME_DEFAULT', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Current Theme
    |--------------------------------------------------------------------------
    |
    | Resolved at boot time by the ThemeManager. Read-only at runtime.
    |
    */

    'current' => null,

    /*
    |--------------------------------------------------------------------------
    | Paths
    |--------------------------------------------------------------------------
    */

    'paths' => [
        'themes' => base_path('themes'),
        'assets' => public_path('themes'),
        'assets_url' => env('THEME_ASSETS_URL', 'themes'),

        'generator' => [
            'views' => 'resources/views',
            'assets' => 'resources/assets',
            'lang' => 'resources/lang',
            'config' => 'config',
            'routes' => 'routes',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Scan Path
    |--------------------------------------------------------------------------
    |
    | Additional theme locations. Useful when themes are hosted in vendor.
    |
    */

    'scan' => [
        'enabled' => false,
        'paths' => [
            base_path('vendor/*/*'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Registration
    |--------------------------------------------------------------------------
    */

    'register' => [
        'translations' => true,
        'files' => 'register',
    ],

    /*
    |--------------------------------------------------------------------------
    | Activators
    |--------------------------------------------------------------------------
    |
    | The file activator stores activation statuses in a JSON file, the same
    | way nwidart/laravel-modules stores module statuses. The database activator
    | stores the active theme name in the current website settings, so each
    | website can only have one active theme at a time.
    |
    */

    'activators' => [
        'file' => [
            'class' => FileActivator::class,
            'statuses-file' => base_path('themes/statuses.json'),
        ],

        'database' => [
            'class' => DatabaseActivator::class,
            'key' => 'theme',
        ],
    ],

    'activator' => env('THEMES_ACTIVATOR', 'database'),

];
