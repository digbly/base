@php
    $routes = collect(\Illuminate\Support\Facades\Route::getRoutes()->getRoutes())
        ->filter(fn ($route) => $route->getName() !== null)
        ->mapWithKeys(fn ($route) => [$route->getName() => '/'.ltrim($route->uri(), '/')])
        ->all();

    $page = [
        'component' => 'NotFound',
        'props' => [
            'siteName' => config('app.name'),
            'messages' => trans('default::messages'),
            'navCategories' => [],
            'sidebarWidgets' => [],
            'routes' => $routes,
        ],
        'url' => request()->getRequestUri(),
        'version' => null,
        'clearHistory' => false,
        'encryptHistory' => false,
    ];
@endphp

{!! view('default::theme', ['page' => $page])->render() !!}
