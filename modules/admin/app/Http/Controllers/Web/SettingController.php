<?php

namespace Modules\Admin\Http\Controllers\Web;

use App\Support\AdminTranslations;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Admin\Http\Controllers\Admin\SettingController as AdminSettingController;
use Modules\Admin\Http\Requests\Admin\SettingRequest;
use Modules\Admin\Support\MediaPreviewResolver;

class SettingController extends AdminSettingController
{
    /**
     * Show the website settings form.
     */
    public function edit(AdminTranslations $translations): Response
    {
        $settings = $this->payload();

        return Inertia::render('Admin::settings/Index', [
            'title' => __('admin.nav.settings'),
            'settings' => $settings,
            'locales' => $translations->locales(),
            'media' => $this->mediaPreviews($settings),
        ]);
    }

    /**
     * Persist the submitted settings.
     */
    public function store(SettingRequest $request): RedirectResponse
    {
        $this->apply($request->validated());

        return back()->with('success', __('admin.settings.notices.saved'));
    }

    /**
     * Resolve the currently assigned branding media so the form can preview it.
     *
     * @param  array<string, mixed>  $settings
     * @return array<string, array<string, mixed>|null>
     */
    protected function mediaPreviews(array $settings): array
    {
        $previews = app(MediaPreviewResolver::class)->byId([
            $settings['logo'] ?? null,
            $settings['favicon'] ?? null,
            $settings['banner'] ?? null,
        ]);

        return [
            'logo' => $previews[$settings['logo'] ?? ''] ?? null,
            'favicon' => $previews[$settings['favicon'] ?? ''] ?? null,
            'banner' => $previews[$settings['banner'] ?? ''] ?? null,
        ];
    }
}
