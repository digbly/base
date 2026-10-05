<?php

namespace Themes\Default\Http\Controllers;

use App\Support\SidebarRenderer;
use Illuminate\Routing\Controller as BaseController;
use Inertia\Inertia;
use Inertia\Response;
use Themes\Default\Support\NavigationData;
use Themes\Default\Support\PostPresenter;

abstract class Controller extends BaseController
{
    /**
     * Render a theme Inertia page with the props every theme page shares.
     *
     * @param  array<string, mixed>  $props
     */
    protected function render(string $component, array $props = []): Response
    {
        return Inertia::render($component, array_merge([
            'siteName' => config('app.name'),
            'messages' => fn () => trans('default::messages'),
            'navCategories' => fn () => app(NavigationData::class)
                ->categories()
                ->map(fn ($category) => PostPresenter::category($category))
                ->values()
                ->all(),
            'sidebarWidgets' => fn () => app(SidebarRenderer::class)->payload('sidebar'),
        ], $props))->rootView('default::theme');
    }
}
